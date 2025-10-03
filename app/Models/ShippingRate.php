<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $fillable = [
        'zone_id','carrier_id','name','code','is_active',
        'price','per_kg','per_item','free_over',
        'min_subtotal','max_subtotal','min_weight','max_weight','min_qty','max_qty',
        'eta_days_min','eta_days_max','currency','sort_order','data',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'price'       => 'decimal:2',
        'per_kg'      => 'decimal:2',
        'per_item'    => 'decimal:2',
        'free_over'   => 'decimal:2',
        'min_subtotal'=> 'decimal:2',
        'max_subtotal'=> 'decimal:2',
        'min_weight'  => 'decimal:3',
        'max_weight'  => 'decimal:3',
        'data'        => 'array',
    ];

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function carrier()
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }
}
