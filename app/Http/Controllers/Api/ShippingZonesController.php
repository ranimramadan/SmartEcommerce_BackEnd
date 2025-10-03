<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRegion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingZonesController extends Controller
{
    // GET /api/shipping-zones
    public function index(Request $request)
    {
        $q = ShippingZone::query()->withCount('regions')->orderBy('sort_order');

        if ($request->filled('q')) {
            $s = $request->get('q');
            $q->where(function ($w) use ($s) {
                $w->where('name','like',"%{$s}%")
                  ->orWhere('code','like',"%{$s}%");
            });
        }

        if ($request->filled('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        $per = min((int) $request->get('per_page', 20), 100);
        return $q->paginate($per);
    }

    // GET /api/shipping-zones/{zone}
    public function show(ShippingZone $zone)
    {
        return $zone->load('regions');
    }

    // POST /api/shipping-zones
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'code'       => ['required','string','max:50', Rule::unique('shipping_zones','code')],
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $zone = ShippingZone::create($data);
        return response()->json($zone, 201);
    }

    // PUT /api/shipping-zones/{zone}
    public function update(Request $request, ShippingZone $zone)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:150',
            'code'       => ['sometimes','string','max:50', Rule::unique('shipping_zones','code')->ignore($zone->id)],
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $zone->update($data);
        return $zone->refresh();
    }

    // DELETE /api/shipping-zones/{zone}
    public function destroy(ShippingZone $zone)
    {
        $zone->delete();
        return response()->json(null, 204);
    }

    // ========== Regions management ==========

    // GET /api/shipping-zones/{zone}/regions
    public function regions(ShippingZone $zone)
    {
        return $zone->regions()->orderBy('id','desc')->get();
    }

    // POST /api/shipping-zones/{zone}/regions
    public function addRegion(Request $request, ShippingZone $zone)
    {
        $data = $request->validate([
            'country'        => 'nullable|string|size:2',
            'state'          => 'nullable|string|max:50',
            'postal_pattern' => 'nullable|string|max:50',
            'rule'           => ['nullable', Rule::in(['include','exclude'])],
        ]);

        if (empty($data['country']) && empty($data['state']) && empty($data['postal_pattern'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one of country/state/postal_pattern is required.',
            ], 422);
        }

        $data['rule'] = $data['rule'] ?? 'include';
        $region = $zone->regions()->create($data);

        return response()->json($region, 201);
    }

    // DELETE /api/shipping-zones/{zone}/regions/{region}
    public function removeRegion(ShippingZone $zone, ShippingZoneRegion $region)
    {
        abort_unless($region->zone_id === $zone->id, 404);
        $region->delete();
        return response()->json(null, 204);
    }
}
