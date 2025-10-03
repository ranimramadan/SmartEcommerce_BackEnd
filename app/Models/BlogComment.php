<?php
// app/Models/BlogComment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    protected $fillable = [
        'post_id','user_id',
        'author_name','author_email',
        'body','status', // pending|approved|rejected
    ];

    // Scopes سريعة
    public function scopeApproved($q){ return $q->where('status','approved'); }
    public function scopePending($q){ return $q->where('status','pending'); }

    // علاقات
    public function post(): BelongsTo { return $this->belongsTo(BlogPost::class, 'post_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
