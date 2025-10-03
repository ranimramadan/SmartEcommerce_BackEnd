<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = ['attribute_id','value'];

    public function attribute() { return $this->belongsTo(Attribute::class); }

    // القيم المستخدمة في متغيرات المنتجات
    public function variants() {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attribute_values');
    }
}
