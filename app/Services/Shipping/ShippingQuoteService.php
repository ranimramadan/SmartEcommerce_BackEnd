<?php
namespace App\Services\Shipping;

use App\Models\{ShippingZone, ShippingRate, Cart};

class ShippingQuoteService
{
    /**
     * طلع عروض الشحن لسلة معيّنة حسب العنوان.
     * $address = ['country'=>'US','state'=>'CA','zip'=>'94016', ...]
     */
    public function quoteForCart(Cart $cart, array $address, ?string $forceCurrency = null): array
    {
        // حمّل العناصر مع المنتج والمتغير
        $cart->loadMissing(['items.productVariant','items.product']);

        $metrics = $this->cartMetrics($cart);

        $zone = $this->matchZone($address);
        if (!$zone) {
            return [];
        }

        // تفادي N+1 على carrier
        $rates = $zone->rates()->where('is_active', true)->with('carrier')->orderBy('sort_order')->get();

        $options = [];
        foreach ($rates as $rate) {
            if (!$this->passesConditions($rate, $metrics)) {
                continue;
            }

            $price = $this->computePrice($rate, $metrics);
            $currency = $forceCurrency ?: ($rate->currency ?: $cart->currency);

            $options[] = [
                'rate_id'  => $rate->id,
                'name'     => $rate->name,
                'code'     => $rate->code,
                'price'    => round((float)$price, 2),
                'currency' => $currency,
                'carrier'  => $rate->carrier ? [
                    'id'   => $rate->carrier->id,
                    'code' => $rate->carrier->code,
                    'name' => $rate->carrier->name,
                ] : null,
                'eta'      => $this->etaText($rate),
            ];
        }

        return $options;
    }

    protected function etaText(ShippingRate $rate): ?string
    {
        $min = $rate->eta_days_min;
        $max = $rate->eta_days_max;
        if ($min && $max) return "{$min}-{$max} days";
        if ($min)         return "{$min}+ days";
        if ($max)         return "up to {$max} days";
        return null;
    }

    protected function computePrice(ShippingRate $rate, array $m): float
    {
        $price = (float) $rate->price;

        // شحن مجاني فوق عتبة؟
        if (!is_null($rate->free_over) && $m['subtotal'] >= (float) $rate->free_over) {
            return 0.0;
        }

        // لكل كغ
        if ((float)$rate->per_kg > 0 && $m['weight'] !== null) {
            $price += (float)$rate->per_kg * (float)$m['weight'];
        }

        // لكل قطعة
        if ((float)$rate->per_item > 0 && $m['qty'] > 0) {
            $price += (float)$rate->per_item * (int)$m['qty'];
        }

        return max(0.0, $price);
    }

    protected function passesConditions(ShippingRate $rate, array $m): bool
    {
        if (!is_null($rate->min_subtotal) && $m['subtotal'] < (float)$rate->min_subtotal) return false;
        if (!is_null($rate->max_subtotal) && $m['subtotal'] > (float)$rate->max_subtotal) return false;

        if (!is_null($rate->min_qty) && $m['qty'] < (int)$rate->min_qty) return false;
        if (!is_null($rate->max_qty) && $m['qty'] > (int)$rate->max_qty) return false;

        if ($m['weight'] !== null) {
            if (!is_null($rate->min_weight) && $m['weight'] < (float)$rate->min_weight) return false;
            if (!is_null($rate->max_weight) && $m['weight'] > (float)$rate->max_weight) return false;
        }

        return true;
    }

    protected function cartMetrics(Cart $cart): array
    {
        $qty = 0;
        $weight = 0.0;
        $hasWeight = false;

        foreach ($cart->items as $ci) {
            $q = (int) $ci->qty;
            $qty += $q;

            // ✅ nullsafe للتفادي الأخطاء لو العلاقات null
            $w = $ci->productVariant?->weight
               ?? $ci->product?->weight
               ?? ($ci->weight ?? null);

            if ($w !== null) {
                $hasWeight = true;
                $weight += (float) $w * $q;
            }
        }

        return [
            'subtotal' => (float) $cart->subtotal,
            'qty'      => $qty,
            'weight'   => $hasWeight ? (float) $weight : null,
        ];
    }

    public function matchZone(array $addr): ?ShippingZone
    {
        $country = strtoupper((string) ($addr['country'] ?? ''));
        $state   = strtoupper((string) ($addr['state'] ?? ''));
        $zip     = strtoupper((string) ($addr['zip'] ?? $addr['postcode'] ?? ''));

        $zones = ShippingZone::where('is_active', true)
            ->with('regions')
            ->orderBy('sort_order')
            ->get();

        foreach ($zones as $zone) {
            if ($zone->regions->isEmpty()) {
                return $zone; // GLOBAL-like zone
            }

            $included = false;
            $excluded = false;

            foreach ($zone->regions as $r) {
                $countryOk = !$r->country || strtoupper($r->country) === $country;
                $stateOk   = !$r->state   || strtoupper($r->state)   === $state;
                $zipOk     = !$r->postal_pattern || $this->matchPattern($r->postal_pattern, $zip);

                if ($countryOk && $stateOk && $zipOk) {
                    if ($r->rule === 'exclude') $excluded = true;
                    else                         $included = true;
                }
            }

            if ($included && !$excluded) {
                return $zone;
            }
        }

        // fallback: GLOBAL
        return ShippingZone::where(['is_active' => true, 'code' => 'GLOBAL'])->first();
    }

    protected function matchPattern(string $pattern, string $value): bool
    {
        if ($pattern === '' || $value === '') return false;
        $regex = '/^' . str_replace(['*','?'], ['.*','.?'], preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $value);
    }
}
