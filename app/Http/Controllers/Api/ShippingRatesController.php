<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{ShippingRate, ShippingZone};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingRatesController extends Controller
{
    // GET /api/shipping-rates
    public function index(Request $request)
    {
        $q = ShippingRate::query()->with(['zone:id,name,code', 'carrier:id,name,code']);

        if ($request->filled('zone_id')) {
            $q->where('zone_id', (int)$request->get('zone_id'));
        }
        if ($request->filled('carrier_id')) {
            $q->where('carrier_id', (int)$request->get('carrier_id'));
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        $q->orderBy('sort_order')->orderBy('id','desc');
        $per = min((int)$request->get('per_page', 20), 100);

        return $q->paginate($per);
    }

    // GET /api/shipping-rates/{rate}
    public function show(ShippingRate $rate)
    {
        return $rate->load('zone:id,name,code','carrier:id,name,code');
    }

    // POST /api/shipping-rates
    public function store(Request $request)
    {
        $data = $request->validate([
            'zone_id'       => ['required','exists:shipping_zones,id'],
            'carrier_id'    => ['nullable','exists:shipping_carriers,id'],
            'name'          => 'required|string|max:150',
            'code'          => 'nullable|string|max:100',
            'is_active'     => 'boolean',
            'price'         => 'numeric|min:0',
            'per_kg'        => 'numeric|min:0',
            'per_item'      => 'numeric|min:0',
            'free_over'     => 'nullable|numeric|min:0',
            'min_subtotal'  => 'nullable|numeric|min:0',
            'max_subtotal'  => 'nullable|numeric|min:0',
            'min_weight'    => 'nullable|numeric|min:0',
            'max_weight'    => 'nullable|numeric|min:0',
            'min_qty'       => 'nullable|integer|min:0',
            'max_qty'       => 'nullable|integer|min:0',
            'eta_days_min'  => 'nullable|integer|min:0',
            'eta_days_max'  => 'nullable|integer|min:0',
            'currency'      => 'nullable|string|size:3',
            'sort_order'    => 'integer|min:0',
            'data'          => 'nullable|array',
        ]);

        $data['is_active'] = $data['is_active'] ?? true;
        $rate = ShippingRate::create($data);

        return response()->json($rate->load('zone:id,name,code','carrier:id,name,code'), 201);
    }

    // PUT /api/shipping-rates/{rate}
    public function update(Request $request, ShippingRate $rate)
    {
        $data = $request->validate([
            'zone_id'       => ['sometimes','exists:shipping_zones,id'],
            'carrier_id'    => ['sometimes','nullable','exists:shipping_carriers,id'],
            'name'          => 'sometimes|string|max:150',
            'code'          => 'sometimes|nullable|string|max:100',
            'is_active'     => 'boolean',
            'price'         => 'sometimes|numeric|min:0',
            'per_kg'        => 'sometimes|numeric|min:0',
            'per_item'      => 'sometimes|numeric|min:0',
            'free_over'     => 'sometimes|nullable|numeric|min:0',
            'min_subtotal'  => 'sometimes|nullable|numeric|min:0',
            'max_subtotal'  => 'sometimes|nullable|numeric|min:0',
            'min_weight'    => 'sometimes|nullable|numeric|min:0',
            'max_weight'    => 'sometimes|nullable|numeric|min:0',
            'min_qty'       => 'sometimes|nullable|integer|min:0',
            'max_qty'       => 'sometimes|nullable|integer|min:0',
            'eta_days_min'  => 'sometimes|nullable|integer|min:0',
            'eta_days_max'  => 'sometimes|nullable|integer|min:0',
            'currency'      => 'sometimes|nullable|string|size:3',
            'sort_order'    => 'sometimes|integer|min:0',
            'data'          => 'sometimes|nullable|array',
        ]);

        $rate->update($data);
        return $rate->refresh()->load('zone:id,name,code','carrier:id,name,code');
    }

    // DELETE /api/shipping-rates/{rate}
    public function destroy(ShippingRate $rate)
    {
        $rate->delete();
        return response()->json(null, 204);
    }
}
