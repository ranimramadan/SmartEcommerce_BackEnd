<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id','brand_id','name','slug','sku','price',
        'short_description','long_description','is_active',
        // ⚠️ لا تضيفي الأعمدة التجميعية هنا
    ];

    // احمي الأعمدة التجميعية من التعديل عبر mass assignment
    protected $guarded = [
        'reviews_count','average_rating',
        'star_1_count','star_2_count','star_3_count','star_4_count','star_5_count',
    ];

    protected $casts = [
        'price'          => 'float',
        'is_active'      => 'boolean',
        'average_rating' => 'float',
        'reviews_count'  => 'integer',
        'star_1_count'   => 'integer',
        'star_2_count'   => 'integer',
        'star_3_count'   => 'integer',
        'star_4_count'   => 'integer',
        'star_5_count'   => 'integer',
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function brand()    { return $this->belongsTo(Brand::class); }
    public function images()   { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function variants() { return $this->hasMany(ProductVariant::class); }

    // مواصفات عامة (غير مكوِّنة للمتغيرات)
    public function specs()    { return $this->hasMany(ProductAttributeValue::class); }

    public function reviews()  { return $this->hasMany(\App\Models\ProductReview::class, 'product_id'); }
}
