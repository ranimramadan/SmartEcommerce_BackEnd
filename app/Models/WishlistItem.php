<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WishlistItem extends Model
{
    protected $fillable = ['wishlist_id','product_id','product_variant_id'];

    public function wishlist()       { return $this->belongsTo(Wishlist::class); }
    public function product()        { return $this->belongsTo(Product::class); }
    public function productVariant() { return $this->belongsTo(ProductVariant::class); }

    public function scopeWithVariant($q)    { return $q->whereNotNull('product_variant_id'); }
    public function scopeWithoutVariant($q) { return $q->whereNull('product_variant_id'); }

    /**
     * انقل هذا البند إلى السلة عبر CartService.
     */
    public function moveToCart(?int $qty = 1, ?\App\Models\Cart $cart = null)
    {
        return DB::transaction(function () use ($qty, $cart) {
            /** @var \App\Services\CartService $svc */
            $svc = app(\App\Services\CartService::class);

            // حددي السياق (مستخدم/ضيف)
            $userId    = $this->wishlist?->user_id;
            $sessionId = $this->wishlist?->session_id;

            if (! $cart) {
                $cart = $svc->getOrCreate($userId, $sessionId);
            }

            // أضفي (أو يدمج تلقائياً داخل الخدمة إذا موجود نفس المنتج/المتغير)
            $cartItem = $svc->addItem(
                $cart,
                $this->product_id,
                $this->product_variant_id,
                max(1, (int)$qty)
            );

            // احذفي بند الويشلست
            $this->delete();

            return $cartItem->fresh(['product','productVariant']);
        });
    }

    public static function moveAllToCart(Wishlist $wishlist, ?\App\Models\Cart $cart = null): int
    {
        $moved = 0;
        foreach ($wishlist->items as $item) {
            $item->moveToCart(1, $cart);
            $moved++;
        }
        return $moved;
    }

    public function getCurrentPriceAttribute()
    {
        return $this->productVariant?->price ?? ($this->product->price ?? 0);
    }

    public function getIsInStockAttribute()
    {
        return $this->productVariant
            ? (bool) ($this->productVariant->isInStock() ?? true)
            : (bool) ($this->product->isInStock() ?? true);
    }
}
