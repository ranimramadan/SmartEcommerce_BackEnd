<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wishlist extends Model
{
    protected $fillable = ['user_id','session_id','share_token'];

    protected $appends = ['share_url', 'items_count'];

    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(WishlistItem::class); }

    public function scopeOwnedBy($q, $userId)   { return $q->where('user_id', $userId); }
    public function scopeForSession($q, $sid)    { return $q->where('session_id', $sid); }

    // إضافة منتج (بدون سنابشوتات—الـfrontend يقرأ من relations)
    public function addProduct(Product $product, ?int $variantId = null)
    {
        return $this->items()->firstOrCreate([
            'product_id'         => $product->id,
            'product_variant_id' => $variantId,
        ]);
    }

    public function removeProduct(int $productId, ?int $variantId = null): int
    {
        return $this->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->delete();
    }

    public function hasProduct(int $productId, ?int $variantId = null): bool
    {
        return $this->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->exists();
    }

    public function clearAll(): int { return $this->items()->delete(); }

    public function getShareUrlAttribute(): string
    {
        return url("/wishlists/{$this->share_token}");
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    protected static function booted(): void
    {
        static::creating(function (Wishlist $w) {
            if (! $w->share_token) $w->share_token = (string) Str::uuid();
        });
    }

    public static function findByShareToken(string $token): ?self
    {
        return static::where('share_token', $token)->first();
    }

    public static function getOrCreateForUserOrSession(?int $userId, ?string $sessionId): self
    {
        if ($userId) {
            return static::firstOrCreate(['user_id' => $userId], ['share_token' => (string) Str::uuid()]);
        }
        return static::firstOrCreate(['session_id' => $sessionId], ['share_token' => (string) Str::uuid()]);
    }
}
