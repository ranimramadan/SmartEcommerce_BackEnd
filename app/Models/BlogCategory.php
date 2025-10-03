<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    protected $fillable = [
        'parent_id','name','slug','image_path','is_active','posts_count','description'
    ];

    public function parent()  { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(){ return $this->hasMany(self::class, 'parent_id'); }
    public function posts()   { return $this->hasMany(BlogPost::class, 'category_id'); }
}
