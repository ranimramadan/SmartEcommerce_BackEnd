<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Wishlist, WishlistItem, Product};

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wl = $this->getOrCreateWishlist($request);
        return $wl->load(['items.product','items.productVariant']);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $wl = $this->getOrCreateWishlist($request);

        WishlistItem::firstOrCreate([
            'wishlist_id'        => $wl->id,
            'product_id'         => $data['product_id'],
            'product_variant_id' => $data['product_variant_id'] ?? null,
        ]);

        return $wl->refresh()->load(['items.product','items.productVariant']);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $wl = $this->getOrCreateWishlist($request);

        $wl->items()
            ->where('product_id', $data['product_id'])
            ->when(isset($data['product_variant_id']), fn($q)=>$q->where('product_variant_id',$data['product_variant_id']))
            ->delete();

        return $wl->refresh()->load(['items.product','items.productVariant']);
    }

    public function clear(Request $request)
    {
        $wl = $this->getOrCreateWishlist($request);
        $wl->items()->delete();
        return response()->json(null, 204);
    }

    private function getOrCreateWishlist(Request $request): Wishlist
    {
        $userId    = optional($request->user())->id;
        $sessionId = (string)($request->header('X-Session-Id')
                    ?: $request->cookie('X-Session-Id')
                    ?: $request->input('session_id',''));

        if (! $userId && $sessionId === '') {
            // أنشئ session id للضيف
            $sessionId = bin2hex(random_bytes(16));
        }

        $wl = Wishlist::getOrCreateForUserOrSession($userId, $sessionId);

        // أرجع الـ session id للضيف لو ما كان مبعوث
        if (! $userId && ! $request->header('X-Session-Id')) {
            cookie()->queue(cookie('X-Session-Id', $sessionId, 60*24*30));
        }

        return $wl;
    }
}
