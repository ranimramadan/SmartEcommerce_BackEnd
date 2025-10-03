<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\{BlogPost, BlogComment};

class BlogCommentsController extends Controller
{
    /* Public */
    // GET /api/blog/public/posts/{post}/comments  (approved فقط)
    public function publicIndex($post)
    {
        $row = BlogPost::query()
            ->where('status','published')
            ->where(fn($w)=>$w->where('id',$post)->orWhere('slug',$post))
            ->firstOrFail(['id']);

        return BlogComment::query()
            ->where('post_id', $row->id)
            ->where('status','approved')
            ->orderBy('id','desc')
            ->paginate(20);
    }

    // POST /api/blog/public/posts/{post}/comments  (ضيف أو مسجّل)
    public function publicStore(Request $r, $post)
    {
        $row = BlogPost::query()
            ->where('status','published')
            ->where(fn($w)=>$w->where('id',$post)->orWhere('slug',$post))
            ->firstOrFail(['id']);

        $data = $r->validate([
            'body'         => 'required|string|min:3',
            'author_name'  => 'nullable|string|max:120',
            'author_email' => 'nullable|email|max:190',
        ]);

        $payload = [
            'post_id'      => $row->id,
            'user_id'      => optional($r->user())->id,
            'body'         => $data['body'],
            'status'       => 'pending', // ينتظر موافقة الأدمن
        ];

        // ضيف: مطلوب اسم/إيميل (إن ما في user)
        if (!$payload['user_id']) {
            $r->validate([
                'author_name'  => 'required|string|max:120',
                'author_email' => 'required|email|max:190',
            ]);
            $payload['author_name']  = $data['author_name'];
            $payload['author_email'] = $data['author_email'];
        }

        $comment = BlogComment::create($payload);
        return response()->json($comment, 201);
    }

    /* Admin */
    // GET /api/blog/comments?status=pending|approved|rejected
    public function adminIndex(Request $r)
    {
        $q = BlogComment::query()->with('post:id,title,slug');

        if ($r->filled('status')) $q->where('status', $r->get('status'));
        if ($r->filled('post_id')) $q->where('post_id', (int)$r->get('post_id'));

        $q->orderByDesc('id');
        return $q->paginate(min((int)$r->query('per_page', 20), 100));
    }

    // PUT /api/blog/comments/{comment}/moderate
    public function adminModerate(Request $r, BlogComment $comment)
    {
        $data = $r->validate([
            'status' => ['required', Rule::in(['approved','rejected','pending'])],
        ]);
        $comment->update(['status'=>$data['status']]);
        return $comment->refresh();
    }

    // DELETE /api/blog/comments/{comment}
    public function adminDestroy(BlogComment $comment)
    {
        $comment->delete();
        return response()->json(null, 204);
    }
}
