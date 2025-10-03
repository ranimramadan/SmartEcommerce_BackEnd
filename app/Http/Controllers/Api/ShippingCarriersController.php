<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingCarrier;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ShippingCarriersController extends Controller
{
    public function index(Request $request)
    {
        $q = ShippingCarrier::query();

        if ($request->filled('q')) {
            $s = $request->get('q');
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%");
            });
        }

        if ($request->filled('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        $q->orderBy('name');
        $per = min((int) $request->get('per_page', 20), 100);
        return $q->paginate($per);
    }

    public function show(ShippingCarrier $carrier)
    {
        return $carrier;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => ['required','string','max:100', Rule::unique('shipping_carriers','code')],
            'is_active' => 'boolean',
            'phone'     => 'nullable|string|max:100',
            'website'   => 'nullable|url|max:255',
        ]);

        $carrier = ShippingCarrier::create($data);

        Log::info('Shipping carrier created', [
            'carrier_id' => $carrier->id,
            'code'       => $carrier->code,
            'by'         => auth()->id(),
        ]);

        return response()->json($carrier, 201);
    }

    public function update(Request $request, ShippingCarrier $carrier)
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'code'      => ['sometimes','string','max:100', Rule::unique('shipping_carriers','code')->ignore($carrier->id)],
            'is_active' => 'boolean',
            'phone'     => 'nullable|string|max:100',
            'website'   => 'nullable|url|max:255',
        ]);

        $carrier->update($data);

        Log::info('Shipping carrier updated', [
            'carrier_id' => $carrier->id,
            'by'         => auth()->id(),
        ]);

        return $carrier->refresh();
    }

    public function destroy(ShippingCarrier $carrier)
    {
        // تحقّق: هل في شحنات مرتبطة بهاي الشركة؟
        $hasShipments = Shipment::where('shipping_carrier_id', $carrier->id)->exists(); // ✅ الاسم الصحيح
        if ($hasShipments) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete carrier with existing shipments.',
            ], 422);
        }

        $id = $carrier->id;
        $carrier->delete();

        Log::warning('Shipping carrier deleted', [
            'carrier_id' => $id,
            'by'         => auth()->id(),
        ]);

        return response()->json(null, 204);
    }
}
