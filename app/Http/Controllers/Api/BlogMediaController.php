<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\{BlogPost, BlogPostImage};

class BlogMediaController extends Controller
{
    /* Public */
    // GET /api/blog/public/posts/{post}/media
    public function publicIndex(BlogPost $post)
    {
        abort_unless($post->status === 'published', 404);

        return $post->images()
            ->orderBy('sort_order')
            ->get(['id','path','alt','sort_order']);
    }

    /* Admin */
    public function adminIndex(BlogPost $post)
    {
        return $post->images()->orderBy('sort_order')->get();
    }

    public function adminStore(Request $req, BlogPost $post)
    {
        $data = $req->validate([
            'file'       => 'required|file|mimes:jpg,jpeg,png,webp,avif,gif|max:5120',
            'alt'        => 'nullable|string|max:190',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $disk = config('filesystems.default', 'public');
        $path = $req->file('file')->store('blog', $disk);

        $image = BlogPostImage::create([
            'post_id'    => $post->id,
            'path'       => $path,
            'alt'        => $data['alt'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json($image, 201);
    }

    public function adminUpdate(Request $req, BlogPostImage $image)
    {
        $data = $req->validate([
            'alt'        => 'sometimes|nullable|string|max:190',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        $image->update($data);
        return $image->refresh();
    }

    public function adminDestroy(BlogPostImage $image)
    {
        if ($image->path && Storage::exists($image->path)) {
            Storage::delete($image->path);
        }
        $image->delete();

        return response()->json(null, 204);
    }
}
