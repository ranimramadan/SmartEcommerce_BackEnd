<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\{BlogPost, BlogCategory, BlogTag};
use Illuminate\Support\Str;

class BlogPostsController extends Controller
{
    /* =========================
     * Public (قراءة فقط)
     * ========================= */

    // GET /api/blog/public/posts
    public function publicIndex(Request $r)
    {
        $q = BlogPost::query()
            ->with(['category:id,name,slug','tags:id,name,slug'])
            ->where('status', 'published');

        if ($s = $r->query('q')) {
            $q->where(function($w) use ($s){
                $w->where('title','like',"%{$s}%")
                  ->orWhere('excerpt','like',"%{$s}%")
                  ->orWhere('content','like',"%{$s}%");
            });
        }

        if ($cat = $r->query('category')) {
            $q->whereHas('category', fn($c)=>$c->where('slug', $cat)->orWhere('id', $cat));
        }

        if ($tag = $r->query('tag')) {
            $q->whereHas('tags', fn($t)=>$t->where('slug', $tag)->orWhere('id', $tag));
        }

        if ($r->filled('from')) $q->whereDate('published_at','>=',$r->date('from'));
        if ($r->filled('to'))   $q->whereDate('published_at','<=',$r->date('to'));

        $q->orderByDesc('published_at')->orderByDesc('id');
        $per = min((int) $r->query('per_page', 12), 100);
        return $q->paginate($per);
    }

    // GET /api/blog/public/posts/{post}  (id أو slug)
    public function publicShow($post)
    {
        $row = BlogPost::query()
            ->where('status','published')
            ->where(function($w) use ($post){
                $w->where('id', $post)->orWhere('slug', $post);
            })
            ->with(['category:id,name,slug','tags:id,name,slug','images:id,post_id,path,alt,sort_order'])
            ->firstOrFail();

        return $row;
    }

    /* =========================
     * Admin (CRUD كامل)
     * ========================= */

    // GET /api/blog/posts
    public function adminIndex(Request $r)
    {
        $q = BlogPost::query()->with(['category:id,name,slug','tags:id,name,slug']);

        if ($r->filled('status')) {
            $q->where('status', $r->get('status')); // draft|published|archived
        }
        if ($s = $r->query('q')) {
            $q->where(function($w) use ($s){
                $w->where('title','like',"%{$s}%")->orWhere('slug','like',"%{$s}%");
            });
        }
        if ($catId = $r->query('category_id')) $q->where('category_id', (int)$catId);

        $q->orderByDesc('id');
        $per = min((int)$r->query('per_page', 20), 100);
        return $q->paginate($per);
    }

    // POST /api/blog/posts
    public function adminStore(Request $r)
    {
        $data = $r->validate([
            'title'       => 'required|string|max:190',
            'slug'        => 'nullable|string|max:200|unique:blog_posts,slug',
            'excerpt'     => 'nullable|string',
            'content'     => 'nullable|string',
            'status'      => ['nullable', Rule::in(['draft','published','archived'])],
            'category_id' => 'nullable|exists:blog_categories,id',
            'published_at'=> 'nullable|date',
            'tag_ids'     => 'array',
            'tag_ids.*'   => 'integer|exists:blog_tags,id',
        ]);

        $data['slug']   = $data['slug'] ?? Str::slug($data['title']);
        $data['status'] = $data['status'] ?? 'draft';

        $post = BlogPost::create($data);
        if (!empty($data['tag_ids'])) {
            $post->tags()->sync($data['tag_ids']);
        }

        return response()->json($post->load('tags','category'), 201);
    }

    // PUT /api/blog/posts/{post}
    public function adminUpdate(Request $r, BlogPost $post)
    {
        $data = $r->validate([
            'title'       => 'sometimes|string|max:190',
            'slug'        => ['sometimes','string','max:200', Rule::unique('blog_posts','slug')->ignore($post->id)],
            'excerpt'     => 'sometimes|nullable|string',
            'content'     => 'sometimes|nullable|string',
            'status'      => ['sometimes', Rule::in(['draft','published','archived'])],
            'category_id' => 'sometimes|nullable|exists:blog_categories,id',
            'published_at'=> 'sometimes|nullable|date',
            'tag_ids'     => 'sometimes|array',
            'tag_ids.*'   => 'integer|exists:blog_tags,id',
        ]);

        $post->update($data);
        if ($r->has('tag_ids')) $post->tags()->sync($data['tag_ids'] ?? []);

        return $post->load('tags','category');
    }

    // DELETE /api/blog/posts/{post}
    public function adminDestroy(BlogPost $post)
    {
        $post->tags()->detach();
        $post->delete();
        return response()->json(null, 204);
    }

    // POST /api/blog/posts/{post}/publish
    public function adminPublish(BlogPost $post)
    {
        $post->update([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);
        return $post->refresh();
    }

    // POST /api/blog/posts/{post}/unpublish
    public function adminUnpublish(BlogPost $post)
    {
        $post->update(['status' => 'draft']);
        return $post->refresh();
    }
}
