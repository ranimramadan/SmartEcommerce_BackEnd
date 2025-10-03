<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductAttributeValue extends Model
{
    use HasFactory;

    protected $fillable = ['product_id','attribute_id','attribute_value_id','value_text'];

    public function product()   { return $this->belongsTo(Product::class); }
    public function attribute() { return $this->belongsTo(Attribute::class); }
    public function value()     { return $this->belongsTo(AttributeValue::class, 'attribute_value_id'); }
}
