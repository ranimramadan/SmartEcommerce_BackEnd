<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\BlogTag;

class BlogTagsController extends Controller
{
    /* Public */
    // GET /api/blog/public/tags
    public function publicIndex(Request $r)
    {
        $q = BlogTag::query()->withCount('posts');
        if ($s = $r->query('q')) {
            $q->where(function($w) use ($s){
                $w->where('name','like',"%{$s}%")->orWhere('slug','like',"%{$s}%");
            });
        }
        return $q->orderByDesc('posts_count')->orderBy('name')->get(['id','name','slug','posts_count']);
    }

    // GET /api/blog/public/tags/{tag} (id|slug)
    public function publicShow($tag)
    {
        return BlogTag::query()
            ->where(fn($w)=>$w->where('id',$tag)->orWhere('slug',$tag))
            ->firstOrFail(['id','name','slug','posts_count']);
    }

    /* Admin */
    // GET /api/blog/tags
    public function adminIndex(Request $r)
    {
        $q = BlogTag::query()->withCount('posts');
        if ($s = $r->query('q')) {
            $q->where(function($w) use ($s){
                $w->where('name','like',"%{$s}%")->orWhere('slug','like',"%{$s}%");
            });
        }
        return $q->orderBy('name')->paginate(min((int)$r->query('per_page', 20), 100));
    }

    // POST /api/blog/tags
    public function adminStore(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:150|unique:blog_tags,slug',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        return response()->json(BlogTag::create($data), 201);
    }

    // PUT /api/blog/tags/{tag}
    public function adminUpdate(Request $r, BlogTag $tag)
    {
        $data = $r->validate([
            'name' => 'sometimes|string|max:120',
            'slug' => ['sometimes','string','max:150', Rule::unique('blog_tags','slug')->ignore($tag->id)],
        ]);
        $tag->update($data);
        return $tag->refresh();
    }

    // DELETE /api/blog/tags/{tag}
    public function adminDestroy(BlogTag $tag)
    {
        $tag->posts()->detach();
        $tag->delete();
        return response()->json(null, 204);
    }
}
