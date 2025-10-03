<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id','sku','price','stock','is_active'];

    public function product() { return $this->belongsTo(Product::class); }

    // القيم المرتبطة بهذا المتغير (لون=أحمر، مقاس=L ...)
    public function values() {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attribute_values');
    }
}
