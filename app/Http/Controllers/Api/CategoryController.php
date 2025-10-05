<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * قائمة التصنيفات (افتراضيًا الجذور) مع عدد الأبناء + ترتيب بالاسم + Pagination
     */
    public function index(Request $request)
    {
        $q = Category::query();

        if ($s = $request->get('search')) {
            $q->where('name', 'like', "%{$s}%");
        }

        if ($request->filled('parent_id')) {
            $q->where('parent_id', $request->integer('parent_id'));
        } else {
            // افتراضيًا نعرض الجذور
            $q->whereNull('parent_id');
        }

        $q->withCount('children')->orderBy('name', 'asc');

        $per = min((int) $request->get('per_page', 50), 100);
        return $q->paginate($per);
    }

    /**
     * شجرة التصنيفات (جذور + أبناء مستوى ثاني) مع كاش لمدة ساعة
     */
    public function tree()
    {
        return Cache::remember('categories_tree', 3600, function () {
            return Category::with('children.children')
                ->whereNull('parent_id')
                ->orderBy('name', 'asc')
                ->get();
        });
    }

    /**
     * عرض تصنيف واحد مع بعض العلاقات
     */
    public function show(Category $category)
    {
        return $category->load('parent', 'children', 'attributes', 'products');
    }

    /**
     * إنشاء تصنيف جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        // لأن الشجرة تغيّرت
        Cache::forget('categories_tree');

        return response()->json($category, 201);
    }

    /**
     * تعديل تصنيف
     */
    public function update(Request $request, Category $category)
    {
        // IDs كل أحفاد هذا التصنيف (حتى لا نجعل الأب أحد أحفاده)
        $descendantIds = self::subtreeIds($category->id);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'parent_id'   => [
                'nullable',
                'exists:categories,id',
                Rule::notIn([$category->id]), // لا يمكن أن يكون الأب هو نفسه
                function ($attr, $value, $fail) use ($descendantIds) {
                    if ($value && in_array((int) $value, $descendantIds, true)) {
                        $fail('Parent category cannot be one of its descendants.');
                    }
                }
            ],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        // لأن الشجرة أو بيانات العرض قد تتغير
        Cache::forget('categories_tree');

        return $category->load('children');
    }

    /**
     * حذف تصنيف
     */
    public function destroy(Category $category)
    {
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        // لأن الشجرة تغيّرت
        Cache::forget('categories_tree');

        return response()->json(null, 204);
    }

    /**
     * مزامنة خصائص (Attributes) التصنيف
     */
    public function syncAttributes(Request $request, Category $category)
    {
        $data = $request->validate([
            'attributes'   => ['required', 'array'],
            'attributes.*' => ['exists:attributes,id'],
        ]);

        $category->attributes()->sync($data['attributes']);

        // تأثيرها يظهر في الشجرة/العرض أحيانًا
        Cache::forget('categories_tree');

        return $category->load('attributes');
    }

    /**
     * إرجاع IDs للفئة + جميع فئاتها الفرعية (DFS بسيط)
     */
    public static function subtreeIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $stack = [$categoryId];

        while (!empty($stack)) {
            $current = array_pop($stack);
            $children = Category::where('parent_id', $current)->pluck('id')->all();
            foreach ($children as $cid) {
                if (!in_array($cid, $ids, true)) {
                    $ids[] = $cid;
                    $stack[] = $cid;
                }
            }
        }

        return $ids;
    }
}
