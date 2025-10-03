<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Services\Shipping\ShippingQuoteService;

class ShippingQuotesController extends Controller
{
    // POST /api/shipping/quote
    // body: { "cart_id": 123, "address": {"country":"US","state":"CA","zip":"94016"} }
    public function quote(Request $request, ShippingQuoteService $quotes)
    {
        $data = $request->validate([
            'cart_id'        => 'required|exists:carts,id',
            'address.country'=> 'required|string|size:2',
            'address.state'  => 'nullable|string|max:50',
            'address.zip'    => 'nullable|string|max:50',
            'address.city'   => 'nullable|string|max:100',
            'currency'       => 'nullable|string|size:3',
        ]);

        $cart    = Cart::with('items')->findOrFail($data['cart_id']);
        $address = $data['address'];
        $currency= $data['currency'] ?? null;

        $options = $quotes->quoteForCart($cart, $address, $currency);

        return response()->json([
            'success' => true,
            'data'    => [
                'cart_id' => $cart->id,
                'options' => $options,
            ],
        ]);
    }
}
