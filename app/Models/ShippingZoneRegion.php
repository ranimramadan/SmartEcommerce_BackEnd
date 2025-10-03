<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZoneRegion extends Model
{
    protected $fillable = [
        'zone_id', 'country', 'state', 'postal_pattern', 'rule',
    ];

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }
}
