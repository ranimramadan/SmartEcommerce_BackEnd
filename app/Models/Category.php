<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name','parent_id','description','image'];

    public function parent()   { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id'); }

    public function attributes() { return $this->belongsToMany(Attribute::class, 'attribute_category'); }

    public function products() { return $this->hasMany(Product::class); }
}
