<?php
// app/Models/BlogPostImage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BlogPostImage extends Model
{
    protected $table = 'blog_post_images';

    protected $fillable = [
        'post_id','path','alt','sort_order',
    ];

    protected $appends = ['url'];

    public function post(): BelongsTo { return $this->belongsTo(BlogPost::class, 'post_id'); }

    // URL مشتق من الـ path
    public function getUrlAttribute(): ?string
    {
        if (!$this->path) return null;
        // بيستخدم الديسك الافتراضي
        return Storage::url($this->path);
    }
}
