<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id','type','first_name','last_name','company','country','state',
        'city','zip','address1','address2','phone','email'
    ];

    protected $casts = [
        'type' => 'string',
    ];

    protected $appends = ['full_name','full_address']; // اختياري

    const TYPE_BILLING  = 'billing';
    const TYPE_SHIPPING = 'shipping';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeBilling($q)  { return $q->where('type', self::TYPE_BILLING); }
    public function scopeShipping($q) { return $q->where('type', self::TYPE_SHIPPING); }

    // Accessors
    public function getFullNameAttribute()
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address1,
            $this->address2,
            $this->city,
            $this->state,
            $this->zip,
            $this->country,
        ]);
        return implode(', ', $parts);
    }

    // Helper
    public function toShippingArray(): array
    {
        return [
            'name'         => $this->full_name,
            'address'      => $this->address1,
            'address_2'    => $this->address2,
            'city'         => $this->city,
            'state'        => $this->state,
            'postal_code'  => $this->zip,
            'country'      => $this->country,
            'phone'        => $this->phone,
            'email'        => $this->email,
        ];
    }
}
