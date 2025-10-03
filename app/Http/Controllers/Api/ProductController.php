<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::query()
            ->with([
                'brand:id,name,slug',
                'category:id,name,parent_id',
                'images',
                'variants.values.attribute',
                'specs.attribute',
                'specs.value',
            ]);

        // بحث نصي بالاسم/sku (المنتج والمتغيرات)
        if ($s = $request->get('q')) {
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhereHas('variants', fn($vq) => $vq->where('sku', 'like', "%{$s}%"));
            });
        }

        // تصفية حسب البراند
        if ($request->filled('brand_id')) {
            $q->where('brand_id', $request->integer('brand_id'));
        }

        // تصفية حسب الفئة + كل فئاتها الفرعية
        if ($request->filled('category_id')) {
            $ids = CategoryController::subtreeIds($request->integer('category_id'));
            $q->whereIn('category_id', $ids);
        }

        // نطاق السعر: إمّا سعر المنتج نفسه أو سعر أي متغير
        if ($request->filled('price_min')) {
            $min = (float) $request->get('price_min');
            $q->where(function ($w) use ($min) {
                $w->where('price', '>=', $min)
                  ->orWhereHas('variants', fn($vq) => $vq->whereNotNull('price')->where('price', '>=', $min));
            });
        }
        if ($request->filled('price_max')) {
            $max = (float) $request->get('price_max');
            $q->where(function ($w) use ($max) {
                $w->where('price', '<=', $max)
                  ->orWhereHas('variants', fn($vq) => $vq->whereNotNull('price')->where('price', '<=', $max));
            });
        }

        // التوفر بالمخزون: أي متغير stock > 0
        if ($request->boolean('in_stock')) {
            $q->whereHas('variants', fn($vq) => $vq->where('stock', '>', 0));
        }

        // تصفية بقيم خصائص (values[] = attribute_value_ids)
        // نضمن وجود "متغير واحد" يملك "كل" القيم المختارة
        $values = (array) $request->input('values', []);
        $values = array_filter(array_map('intval', $values));
        if (!empty($values)) {
            $q->whereHas('variants', function ($vq) use ($values) {
                foreach ($values as $valId) {
                    $vq->whereHas('values', fn($vvq) => $vvq->where('attribute_values.id', $valId));
                }
            });
        }

        // الفرز
        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'oldest'     => $q->orderBy('created_at', 'asc'),
            'price_asc'  => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            'name_asc'   => $q->orderBy('name', 'asc'),
            'name_desc'  => $q->orderBy('name', 'desc'),
            default      => $q->orderBy('created_at', 'desc'), // latest
        };

        $per = min((int) $request->get('per_page', 12), 100);

        return $q->paginate($per);
    }

    public function show(Product $product)
    {
        return $product->load(
            'brand','category.parent',
            'images',
            'specs.attribute','specs.value',
            'variants.values.attribute'
        );
    }

    public function store(Request $request)
    {
        $specs    = $this->decodeJsonField($request->input('specs'));
        $variants = $this->decodeJsonField($request->input('variants'));

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:products,slug',
            'sku'         => 'nullable|string|max:255|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'brand_id'    => 'nullable|exists:brands,id',
            'price'       => 'required|numeric|min:0',
            'short_description' => 'nullable|string',
            'long_description'  => 'nullable|string',
            'is_active'   => 'boolean',
            'images'      => 'nullable|array',
            'images.*'    => 'file|image|max:5120',
        ]);

        // توليد slug و SKU تلقائيًا إن لم يُرسلا
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['sku']  = $data['sku']  ?? $this->generateSku('PRD');

        $product = DB::transaction(function () use ($data, $request, $specs, $variants) {

            // 1) منتج
            $product = Product::create($data);

            // 2) صور
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $i => $file) {
                    $path = $file->store('products', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'is_main'    => $i === 0,
                        'sort_order' => $i,
                    ]);
                }
            }

            // 3) مواصفات عامة (specs)
            foreach ($specs as $spec) {
                $product->specs()->create([
                    'attribute_id'       => $spec['attribute_id'],
                    'attribute_value_id' => $spec['attribute_value_id'] ?? null,
                    'value_text'         => $spec['value_text'] ?? null,
                ]);
            }

            // 4) متغيرات
            foreach ($variants as $v) {
                $variantSku = $v['sku'] ?? $this->generateVariantSku($product);
                $variant = $product->variants()->create([
                    'sku'       => $variantSku,
                    'price'     => $v['price'] ?? null,
                    'stock'     => $v['stock'] ?? 0,
                    'is_active' => $v['is_active'] ?? true,
                ]);

                if (!empty($v['values'])) {
                    $variant->values()->sync($v['values']);
                }
            }

            return $product;
        });

        // لوج بعد الإنشاء
        Log::info('Product created', [
            'product_id'      => $product->id,
            'created_by'      => auth()->id(),
            'variants_count'  => is_array($variants) ? count($variants) : 0,
            'images_uploaded' => $request->hasFile('images') ? count($request->file('images')) : 0,
        ]);

        return response()->json(
            $product->load('brand','category','images','specs.attribute','specs.value','variants.values.attribute'),
            201
        );
    }

    public function update(Request $request, Product $product)
    {
        $specs    = $this->decodeJsonField($request->input('specs'));
        $variants = $this->decodeJsonField($request->input('variants'));

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => ['sometimes','string','max:255', Rule::unique('products','slug')->ignore($product->id) ],
            'sku'         => ['nullable','string','max:255',   Rule::unique('products','sku')->ignore($product->id) ],
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id'    => 'nullable|exists:brands,id',
            'price'       => 'sometimes|numeric|min:0',
            'short_description' => 'nullable|string',
            'long_description'  => 'nullable|string',
            'is_active'   => 'boolean',
            'images'      => 'nullable|array',
            'images.*'    => 'file|image|max:5120',
        ]);

        $product = DB::transaction(function () use ($product, $data, $request, $specs, $variants) {

            // تحديث أساسي
            if (isset($data['name']) && !isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }
            if (isset($data['sku']) && $data['sku'] === '') {
                unset($data['sku']); // لا نضعه فارغ
            }
            $product->update($data);

            // صور إضافية
            if ($request->hasFile('images')) {
                $start = ($product->images()->max('sort_order') ?? -1) + 1;
                foreach ($request->file('images') as $i => $file) {
                    $path = $file->store('products', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'is_main'    => false,
                        'sort_order' => $start + $i,
                    ]);
                }
            }

            // استبدال المواصفات إن تم تمريرها
            if (!empty($specs)) {
                $product->specs()->delete();
                foreach ($specs as $spec) {
                    $product->specs()->create([
                        'attribute_id'       => $spec['attribute_id'],
                        'attribute_value_id' => $spec['attribute_value_id'] ?? null,
                        'value_text'         => $spec['value_text'] ?? null,
                    ]);
                }
            }

            // استبدال المتغيرات إن مرّرت
            if (!empty($variants)) {
                foreach ($product->variants as $old) {
                    $old->values()->detach();
                    $old->delete();
                }

                foreach ($variants as $v) {
                    $variantSku = $v['sku'] ?? $this->generateVariantSku($product);
                    $variant = $product->variants()->create([
                        'sku'       => $variantSku,
                        'price'     => $v['price'] ?? null,
                        'stock'     => $v['stock'] ?? 0,
                        'is_active' => $v['is_active'] ?? true,
                    ]);

                    if (!empty($v['values'])) {
                        $variant->values()->sync($v['values']);
                    }
                }
            }

            return $product;
        });

        // لوج بعد التحديث
        Log::info('Product updated', [
            'product_id'     => $product->id,
            'updated_by'     => auth()->id(),
            'images_added'   => $request->hasFile('images') ? count($request->file('images')) : 0,
            'variants_reset' => !empty($variants),
        ]);

        return $product->load('brand','category','images','specs.attribute','specs.value','variants.values.attribute');
    }

    public function destroy(Product $product)
    {
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }
        $id = $product->id;
        $product->delete();

        Log::warning('Product deleted', [
            'product_id' => $id,
            'by_user_id' => auth()->id(),
        ]);

        return response()->json(null, 204);
    }

    public function addImages(Request $request, Product $product)
    {
        $data = $request->validate([
            'images'   => 'required|array|min:1',
            'images.*' => 'file|image|max:5120',
            'main'     => 'nullable|integer|min:0', // index للصورة الرئيسية
        ]);

        $start = ($product->images()->max('sort_order') ?? -1) + 1;

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store('products', 'public');
            $product->images()->create([
                'image_path' => $path,
                'is_main'    => $request->integer('main', -1) === $i,
                'sort_order' => $start + $i,
            ]);
        }

        Log::info('Product images added', [
            'product_id'     => $product->id,
            'added_count'    => count($request->file('images')),
            'main_index'     => $request->integer('main', -1),
            'by_user_id'     => auth()->id(),
        ]);

        return $product->load('images');
    }

    public function removeImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        Log::info('Product image removed', [
            'product_id' => $product->id,
            'image_id'   => $image->id,
            'by_user_id' => auth()->id(),
        ]);

        return response()->json(null, 204);
    }

    private function decodeJsonField($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [];
        }
        return [];
    }

    /** توليد SKU فريد للمنتج */
    private function generateSku(string $prefix = 'PRD'): string
    {
        do {
            $sku = strtoupper($prefix) . '-' . Str::upper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /** توليد SKU فريد للمتغير (يعتمد على SKU المنتج) */
    private function generateVariantSku(Product $product): string
    {
        $base = $product->sku ?: Str::slug($product->name, '-');
        do {
            $sku = strtoupper($base) . '-' . Str::upper(Str::random(4));
        } while (ProductVariant::where('sku', $sku)->exists());

        return $sku;
    }
}
