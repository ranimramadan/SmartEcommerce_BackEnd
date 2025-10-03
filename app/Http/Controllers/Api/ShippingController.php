<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\{Order, Shipment, ShipmentItem, ShippingCarrier};
use App\Services\{ShipmentService, TrackingService};

class ShippingController extends Controller
{
    /* =======================
     * شركات الشحن (أدمن)
     * ======================= */
    public function carriersIndex(Request $request)
    {
        return ShippingCarrier::orderBy('name')->get();
    }

    public function carriersStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:190',
            'code'       => 'required|string|max:50|unique:shipping_carriers,code',
            'is_active'  => 'boolean',
            'config'     => 'nullable|array',
        ]);

        return ShippingCarrier::create($data);
    }

    public function carriersUpdate(Request $request, ShippingCarrier $carrier)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:190',
            'code'       => ['sometimes','string','max:50', Rule::unique('shipping_carriers','code')->ignore($carrier->id)],
            'is_active'  => 'boolean',
            'config'     => 'nullable|array',
        ]);

        $carrier->update($data);
        return $carrier;
    }

    public function carriersDestroy(ShippingCarrier $carrier)
    {
        $carrier->delete();
        return response()->json(null, 204);
    }

    /* =======================
     * الشحنات (عام + أدمن)
     * ======================= */

    /** إنشاء شحنة لطلب */
    public function shipmentsCreate(Request $request, Order $order)
    {
        $data = $request->validate([
            'carrier_id'      => 'required|exists:shipping_carriers,id',
            'items'           => 'required|array|min:1', // [{order_item_id, qty}]
            'tracking_number' => 'nullable|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $shipment = app(ShipmentService::class)->createShipment($order, $data['carrier_id'], $data['items'], $data['tracking_number'] ?? null, $data['notes'] ?? null);

        return $shipment->load('items','carrier','order');
    }

    public function shipmentsShow(Shipment $shipment)
    {
        return $shipment->load('items','carrier','order');
    }

    /** تحديث رقم التتبع أو الحالة */
    public function shipmentsUpdate(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'tracking_number' => 'nullable|string|max:100',
            'status'          => 'nullable|string|max:50', // حسب نموذجك
            'notes'           => 'nullable|string|max:500',
        ]);

        $shipment->update(array_filter($data, fn($v) => !is_null($v)));
        return $shipment->refresh();
    }

    /** تتبع عام برقم التتبع */
    public function trackByNumber(Request $request)
    {
        $data = $request->validate(['tracking_number' => 'required|string|max:100']);
        $info = app(TrackingService::class)->track($data['tracking_number']);
        return response()->json(['tracking' => $info]);
    }
}
