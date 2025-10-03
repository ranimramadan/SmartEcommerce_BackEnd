<?php
// app/Http/Controllers/Api/ShipmentItemsController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Shipment, ShipmentItem};
use App\Services\ShipmentService;
use Illuminate\Http\Request;

class ShipmentItemsController extends Controller
{
    // POST /api/shipments/{shipment}/items
    public function store(Request $req, Shipment $shipment)
    {
        $data = $req->validate([
            'order_item_id' => 'required|integer|exists:order_items,id',
            'qty'           => 'required|integer|min:1',
        ]);

        $item = app(ShipmentService::class)->addItem(
            $shipment,
            (int)$data['order_item_id'],
            (int)$data['qty']
        );

        return response()->json($item->load('orderItem'), 201);
    }

    // PUT /api/shipment-items/{item}
    public function updateQty(Request $req, ShipmentItem $item)
    {
        $data = $req->validate(['qty' => 'required|integer|min:1']);
        if (! $item->updateQuantity((int)$data['qty'])) {
            return response()->json(['message'=>'Quantity exceeds remaining to ship'], 422);
        }
        return $item->refresh()->load('orderItem');
    }

    // DELETE /api/shipment-items/{item}
    public function destroy(ShipmentItem $item)
    {
        $item->delete();
        return response()->json(null, 204);
    }
}
