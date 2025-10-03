<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
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

    public function tree()
    {
        return Cache::remember('categories_tree', 3600, function() {
            return Category::with('children.children')
                ->whereNull('parent_id')
                ->orderBy('name', 'asc')
                ->get();
        });
    }

    public function show(Category $category)
    {
        return $category->load('parent', 'children', 'attributes', 'products');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'parent_id'   => 'nullable|exists:categories,id|not_in:'.$category->id,
            'description' => 'nullable|string',
            'image'       => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) Storage::disk('public')->delete($category->image);
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);
        return $category->load('children');
    }

    public function destroy(Category $category)
    {
        if ($category->image) Storage::disk('public')->delete($category->image);
        $category->delete();
        return response()->json(null, 204);
    }

    public function syncAttributes(Request $request, Category $category)
    {
        $data = $request->validate([
            'attributes'   => 'required|array',
            'attributes.*' => 'exists:attributes,id'
        ]);

        $category->attributes()->sync($data['attributes']);
        return $category->load('attributes');
    }

    /**
     * إرجاع IDs للفئة + جميع فئاتها الفرعية (مفيد للفلترة)
     */
    public static function subtreeIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $stack = [$categoryId];

        while (!empty($stack)) {
            $current = array_pop($stack);
            $children = Category::where('parent_id', $current)->pluck('id')->all();
            foreach ($children as $cid) {
                if (!in_array($cid, $ids)) {
                    $ids[] = $cid;
                    $stack[] = $cid;
                }
            }
        }
        return $ids;
    }
}
