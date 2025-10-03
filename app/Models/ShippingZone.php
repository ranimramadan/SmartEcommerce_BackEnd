<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = [
        'name', 'code', 'is_active', 'sort_order',
    ];

    public function regions()
    {
        return $this->hasMany(ShippingZoneRegion::class, 'zone_id');
    }

    public function rates()
    {
        return $this->hasMany(ShippingRate::class, 'zone_id')->orderBy('sort_order');
    }
}
