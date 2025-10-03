<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // ✅ إضافة لاستخدام التوليد العشوائي

class ShipmentsController extends Controller
{
    // GET /api/shipments
    public function index(Request $request)
    {
        $q = Shipment::query()->with([
            'order:id,order_number,user_id,fulfillment_status',
            'carrier:id,name,code',
        ]);

        if ($request->filled('order_id')) {
            $q->where('order_id', (int)$request->get('order_id'));
        }

        if ($request->filled('carrier_id')) {
            $q->where('shipping_carrier_id', (int)$request->get('carrier_id')); // ✅ الاسم الصحيح
        }

        if ($request->filled('status')) {
            $request->validate([
                'status' => [Rule::in([
                    'label_created','in_transit','out_for_delivery','delivered','failed','returned'
                ])],
            ]);
            $q->where('status', $request->get('status'));
        }

        if ($request->filled('tracking_number')) {
            $q->where('tracking_number', 'like', '%'.$request->get('tracking_number').'%');
        }

        $q->orderByDesc('created_at');
        $per = min((int)$request->get('per_page', 20), 100);
        return $q->paginate($per);
    }

    // GET /api/shipments/{shipment}
    public function show(Shipment $shipment)
    {
        // ما نحدّد أعمدة للـcarrier فيها tracking_url (مش عمود حقيقي)
        return $shipment->load([
            'order:id,order_number,fulfillment_status',
            'carrier:id,name,code',
            'events' => fn($q) => $q->orderBy('happened_at','desc'),
            'items.orderItem:id,order_id,sku,name,qty',
        ]);
    }

    // POST /api/shipments
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id'        => ['required','exists:orders,id'],
            'carrier_id'      => ['nullable','exists:shipping_carriers,id'],
            'tracking_number' => ['nullable','string','max:100'],
            'status'          => ['nullable', Rule::in(['label_created','in_transit','out_for_delivery','delivered','failed','returned'])],
            'shipped_at'      => 'nullable|date',
            'delivered_at'    => 'nullable|date|after_or_equal:shipped_at',
            'note'            => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($data['order_id']);

        $shipment = DB::transaction(function () use ($order, $data) {
            $status     = $data['status'] ?? 'label_created';
            $carrierId  = $data['carrier_id'] ?? null;
            $tracking   = $data['tracking_number'] ?? null;

            // 🔹 توليد رقم تتبّع تلقائي لو الناقل داخلي وما في رقم متاح
            if ($carrierId) {
                $carrier = \App\Models\ShippingCarrier::find($carrierId);
                if ($carrier && $carrier->code === \App\Models\ShippingCarrier::CARRIER_INTERNAL && empty($tracking)) {
                    do {
                        $tracking = 'INT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
                    } while (
                        \App\Models\Shipment::where('shipping_carrier_id', $carrierId)
                            ->where('tracking_number', $tracking)
                            ->exists()
                    );
                }
            }

            /** @var Shipment $shipment */
            $shipment = Shipment::create([
                'order_id'            => $order->id,
                'shipping_carrier_id' => $carrierId,          // ✅
                'tracking_number'     => $tracking,           // ✅ مرّر الرقم (أصلي أو مولّد)
                'status'              => $status,
                'shipped_at'          => $data['shipped_at']   ?? null,
                'delivered_at'        => $data['delivered_at'] ?? null,
            ]);

            // سجلّ حدث (shipment_events: code/description/happened_at)
            if (method_exists($shipment, 'events')) {
                $shipment->events()->create([
                    'code'        => $status,                  // ✅ code وليس status
                    'description' => $data['note'] ?? 'Created',
                    'happened_at' => now(),
                ]);
            }

            // ضبط تواريخ قياسية
            if (in_array($status, ['in_transit','out_for_delivery'], true) && is_null($shipment->shipped_at)) {
                $shipment->forceFill(['shipped_at' => now()])->save();
            }
            if ($status === 'delivered' && is_null($shipment->delivered_at)) {
                $shipment->forceFill(['delivered_at' => now()])->save();
            }

            // حدّث Fulfillment
            $shipment->refreshOrderFulfillment();

            // هوكس اختيارية
            if (class_exists(\App\Services\ShipmentService::class) && method_exists(app(\App\Services\ShipmentService::class), 'onShipmentCreated')) {
                try {
                    app(\App\Services\ShipmentService::class)->onShipmentCreated($shipment);
                } catch (\Throwable $e) {
                    Log::warning('ShipmentService::onShipmentCreated failed', ['shipment_id'=>$shipment->id,'error'=>$e->getMessage()]);
                }
            }

            return $shipment;
        });

        return response()->json(
            $shipment->load('order:id,order_number,fulfillment_status','carrier:id,name,code'),
            201
        );
    }

    // PUT /api/shipments/{shipment}
    public function update(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'carrier_id'      => ['sometimes','nullable','exists:shipping_carriers,id'],
            'tracking_number' => ['sometimes','nullable','string','max:100'],
            'status'          => ['sometimes', Rule::in(['label_created','in_transit','out_for_delivery','delivered','failed','returned'])],
            'shipped_at'      => 'sometimes|nullable|date',
            'delivered_at'    => 'sometimes|nullable|date|after_or_equal:shipped_at',
            'note'            => 'sometimes|nullable|string|max:500',
        ]);

        DB::transaction(function () use ($shipment, $data) {
            $old = $shipment->status;

            $patch = $data;
            if (array_key_exists('carrier_id', $patch)) {
                $patch['shipping_carrier_id'] = $patch['carrier_id']; // ✅ تحويل الاسم
                unset($patch['carrier_id']);
            }
            $shipment->update($patch);

            if (isset($data['status']) && $data['status'] !== $old && method_exists($shipment, 'events')) {
                $shipment->events()->create([
                    'code'        => $data['status'], // ✅
                    'description' => $data['note'] ?? "Status updated: {$old} → {$data['status']}",
                    'happened_at' => now(),
                ]);
            }

            if (in_array($shipment->status, ['in_transit','out_for_delivery'], true) && is_null($shipment->shipped_at)) {
                $shipment->forceFill(['shipped_at' => now()])->save();
            }
            if ($shipment->status === 'delivered' && is_null($shipment->delivered_at)) {
                $shipment->forceFill(['delivered_at' => now()])->save();
            }

            $shipment->refreshOrderFulfillment();

            if (class_exists(\App\Services\ShipmentService::class) && method_exists(app(\App\Services\ShipmentService::class), 'onShipmentUpdated')) {
                try {
                    app(\App\Services\ShipmentService::class)->onShipmentUpdated($shipment, $old);
                } catch (\Throwable $e) {
                    Log::warning('ShipmentService::onShipmentUpdated failed', ['shipment_id'=>$shipment->id,'error'=>$e->getMessage()]);
                }
            }
        });

        return $shipment->refresh()->load('order:id,order_number,fulfillment_status','carrier:id,name,code');
    }

    // POST /api/shipments/{shipment}/events
    public function addEvent(Request $request, Shipment $shipment)

{
    $data = $request->validate([
        'code'        => ['required', Rule::in([
            'label_created','pickup','in_transit','hub_scan','out_for_delivery','delivered','failed','returned'
        ])],
        'description' => 'nullable|string|max:1000',
        'location'    => 'nullable|string|max:190',
        'happened_at' => 'nullable|date',
    ]);

    // ✅ لو Failed/Returned لازم سبب واضح
    if (in_array($data['code'], ['failed','returned'], true) && empty($data['description'])) {
        return response()->json([
            'message' => 'Description is required when code is failed/returned.'
        ], 422);
    }

    if (!method_exists($shipment, 'events')) {
        return response()->json(['success'=>false,'message'=>'Shipment events relation not available.'], 422);
    }

    $event = $shipment->events()->create([
        'code'        => $data['code'],
        'description' => $data['description'] ?? null,
        'location'    => $data['location'] ?? null,
        'happened_at' => $data['happened_at'] ?? now(),
    ]);

    // موديل ShipmentEvent رح يطبّق الأثر على الشحنة (applyToShipment)
    return response()->json($event->load('shipment'), 201);
}


    // POST /api/shipments/{shipment}/cancel
    public function cancel(Shipment $shipment)
    {
        if ($shipment->status === 'returned' || $shipment->status === 'failed') {
            return $shipment; // معتبَرين كحالات فشل/إرجاع
        }

        DB::transaction(function () use ($shipment) {
            $old = $shipment->status;
            $shipment->update(['status' => 'failed']); // أو returned حسب تدفقك

            if (method_exists($shipment, 'events')) {
                $shipment->events()->create([
                    'code'        => 'failed', // ✅
                    'description' => "Shipment canceled (was {$old})",
                    'happened_at' => now(),
                ]);
            }

            $shipment->refreshOrderFulfillment();

            if (class_exists(\App\Services\ShipmentService::class) && method_exists(app(\App\Services\ShipmentService::class), 'onShipmentCanceled')) {
                try {
                    app(\App\Services\ShipmentService::class)->onShipmentCanceled($shipment);
                } catch (\Throwable $e) {
                    Log::warning('ShipmentService::onShipmentCanceled failed', ['shipment_id'=>$shipment->id,'error'=>$e->getMessage()]);
                }
            }
        });

        return $shipment->refresh();
    }

    // POST /api/shipments/{shipment}/mark-delivered
    public function markDelivered(Shipment $shipment)
    {
        if ($shipment->status === 'delivered') {
            return $shipment;
        }

        DB::transaction(function () use ($shipment) {
            $shipment->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            if (method_exists($shipment, 'events')) {
                $shipment->events()->create([
                    'code'        => 'delivered', // ✅
                    'description' => 'Shipment delivered',
                    'happened_at' => now(),
                ]);
            }

            $shipment->refreshOrderFulfillment();

            if (class_exists(\App\Services\ShipmentService::class) && method_exists(app(\App\Services\ShipmentService::class), 'onShipmentDelivered')) {
                try {
                    app(\App\Services\ShipmentService::class)->onShipmentDelivered($shipment);
                } catch (\Throwable $e) {
                    Log::warning('ShipmentService::onShipmentDelivered failed', ['shipment_id'=>$shipment->id,'error'=>$e->getMessage()]);
                }
            }
        });

        return $shipment->refresh();
    }
}
