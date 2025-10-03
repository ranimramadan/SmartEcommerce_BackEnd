<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\BlogCategory;

class BlogCategoriesController extends Controller
{
    /* Public */
    // GET /api/blog/public/categories
    public function publicIndex()
    {
        return BlogCategory::query()
            ->withCount(['posts as published_posts_count' => fn($q)=>$q->where('status','published')])
            ->orderBy('name')->get(['id','name','slug','description']);
    }

    // GET /api/blog/public/categories/{category} (id|slug)
    public function publicShow($category)
    {
        return BlogCategory::query()
            ->where(fn($w)=>$w->where('id',$category)->orWhere('slug',$category))
            ->firstOrFail(['id','name','slug','description']);
    }

    /* Admin */
    // GET /api/blog/categories
    public function adminIndex(Request $r)
    {
        $q = BlogCategory::query()->withCount('posts');
        if ($s = $r->query('q')) {
            $q->where(function($w) use ($s){
                $w->where('name','like',"%{$s}%")->orWhere('slug','like',"%{$s}%");
            });
        }
        return $q->orderBy('name')->paginate(min((int)$r->query('per_page', 20), 100));
    }

    // POST /api/blog/categories
    public function adminStore(Request $r)
    {
        $data = $r->validate([
            'name'        => 'required|string|max:120',
            'slug'        => 'nullable|string|max:150|unique:blog_categories,slug',
            'description' => 'nullable|string',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        return response()->json(BlogCategory::create($data), 201);
    }

    // PUT /api/blog/categories/{category}
    public function adminUpdate(Request $r, BlogCategory $category)
    {
        $data = $r->validate([
            'name'        => 'sometimes|string|max:120',
            'slug'        => ['sometimes','string','max:150', Rule::unique('blog_categories','slug')->ignore($category->id)],
            'description' => 'sometimes|nullable|string',
        ]);
        $category->update($data);
        return $category->refresh();
    }

    // DELETE /api/blog/categories/{category}
    public function adminDestroy(BlogCategory $category)
    {
        // ملاحظة: لو بدك منع حذف وفيها بوستات، تحقّقي هنا
        $category->delete();
        return response()->json(null, 204);
    }
}
