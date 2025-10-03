<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'category_id','author_id','title','slug','excerpt','content',
        'cover_image_path','is_published','published_at','meta','view_count'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'meta'         => 'array',
    ];

    // علاقات
    public function category() { return $this->belongsTo(BlogCategory::class, 'category_id'); }
    public function author()   { return $this->belongsTo(User::class, 'author_id'); }

    public function tags()     { return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id'); }
    public function images()   { return $this->hasMany(BlogPostImage::class, 'post_id'); }
    public function comments() { return $this->hasMany(BlogComment::class, 'post_id'); }
    
    // سكوبات مفيدة
    public function scopePublished($q)  { return $q->where('is_published', true)->whereNotNull('published_at'); }
    public function scopeRecent($q)     { return $q->orderByDesc('published_at'); }
    public function scopeInCategory($q, $catId) { return $q->where('category_id', $catId); }
}
