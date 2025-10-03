<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'sku',
        'name',
        'price',
        'qty',
        'line_subtotal',
        'line_discount',
        'line_total',
        'options',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'line_discount' => 'decimal:2',
        'line_total'    => 'decimal:2',
        'qty'           => 'integer',
        'options'       => 'array',
    ];

    /* ---------- علاقات ---------- */
    public function cart()           { return $this->belongsTo(Cart::class); }
    public function product()        { return $this->belongsTo(Product::class); }
    public function productVariant() { return $this->belongsTo(ProductVariant::class); }

    /* ---------- هيلبرز ---------- */
    public function updateQuantity(int $qty): self
    {
        $this->qty = max(1, $qty);
        $this->save(); // سيُعيد حساب السطور عبر حدث saving
        return $this;
    }

    public function syncWithProduct(): self
    {
        $product = $this->product;
        $variant = $this->productVariant;

        // snapshots
        $this->name  = $product?->name ?? $this->name;
        $this->sku   = $variant ? $variant->sku : ($product?->sku ?? $this->sku);
        $this->price = $variant && $variant->price !== null ? $variant->price : ($product?->price ?? $this->price);

        return $this;
    }

    public function hasVariant(): bool
    {
        return !is_null($this->product_variant_id);
    }

    public static function findExistingItem($cartId, $productId, $variantId = null)
    {
        return static::where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->where(function ($q) use ($variantId) {
                if (is_null($variantId)) $q->whereNull('product_variant_id');
                else $q->where('product_variant_id', $variantId);
            })
            ->first();
    }

    /* ---------- أكسِسورات ---------- */
    public function getFormattedPriceAttribute()
    {
        return number_format((float)$this->price, 2);
    }

    public function getFormattedTotalAttribute()
    {
        return number_format((float)$this->line_total, 2);
    }

    /* ---------- أحداث الموديل ---------- */
    protected static function booted()
    {
        // قبل الحفظ: حساب السطور بدون save() إضافي
        static::saving(function (CartItem $item) {
            $item->qty = max(1, (int) $item->qty);

            // إكمال snapshot إن لزم
            if ((!$item->name || !$item->price) && ($item->relationLoaded('product') || $item->product()->exists())) {
                $item->syncWithProduct();
            }

            $lineSubtotal = (float) $item->price * (int) $item->qty;
            $lineDiscount = max(0.0, (float) $item->line_discount);
            if ($lineDiscount > $lineSubtotal) {
                $lineDiscount = $lineSubtotal; // لا يتجاوز
            }

            $item->line_subtotal = $lineSubtotal;
            $item->line_discount = $lineDiscount;
            $item->line_total    = $lineSubtotal - $lineDiscount;
        });

        // بعد الحفظ/الحذف: أعِد حساب إجمالي الكارت
        static::saved(function (CartItem $item) {
            $item->cart()->first()?->recalculateTotals();
        });

        static::deleted(function (CartItem $item) {
            if ($item->cart_id) {
                $cart = Cart::find($item->cart_id);
                $cart?->recalculateTotals();
            }
        });
    }
}
