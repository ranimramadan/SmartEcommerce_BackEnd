<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentEvent extends Model
{
    protected $fillable = ['shipment_id','code','description','location','happened_at'];
    protected $casts    = ['happened_at' => 'datetime'];
    protected $appends  = ['formatted_date','code_label'];

    // أكواد مسموحة (شيّكيها مع واجهتك)
    public const ALLOWED_CODES = [
        'label_created','pickup','in_transit','hub_scan',
        'out_for_delivery','delivered','failed','returned'
    ];

    // ربط كود الحدث بحالة الشحنة
    public const MAP_CODE_TO_STATUS = [
        'label_created'     => Shipment::STATUS_LABEL_CREATED,
        'pickup'            => Shipment::STATUS_IN_TRANSIT,
        'in_transit'        => Shipment::STATUS_IN_TRANSIT,
        'hub_scan'          => Shipment::STATUS_IN_TRANSIT,
        'out_for_delivery'  => Shipment::STATUS_OUT_FOR_DELIVERY,
        'delivered'         => Shipment::STATUS_DELIVERED,
        'failed'            => Shipment::STATUS_FAILED,
        'returned'          => Shipment::STATUS_RETURNED,
    ];

    // علاقات
    public function shipment() { return $this->belongsTo(Shipment::class); }

    // سكوبات
    public function scopeLatest($q)   { return $q->orderBy('happened_at','desc'); }
    public function scopeByCode($q,$code) { return $q->where('code',$code); }
    public function scopeBetween($q,$from,$to) {
        return $q->whereBetween('happened_at', [$from,$to]);
    }
    public function scopeFrom($q, $from) { return $q->where('happened_at', '>=', $from); }
public function scopeTo($q, $to)     { return $q->where('happened_at', '<=', $to); }



    // Accessors
    public function getFormattedDateAttribute() { return $this->happened_at?->format('Y-m-d H:i:s'); }
    public function getCodeLabelAttribute()     { return $this->code ? ucwords(str_replace('_',' ',$this->code)) : null; }

    // صناعي
    public static function recordEvent(int $shipmentId, string $code, ?string $description=null, ?string $location=null): self
    {
        return static::create([
            'shipment_id' => $shipmentId,
            'code'        => $code,
            'description' => $description,
            'location'    => $location,
            'happened_at' => now(),
        ]);
    }

    // تطبيق الحدث على الشحنة
    public function applyToShipment(): void
    {
        $shipment = $this->shipment;
        if (! $shipment) return;

        $status = self::MAP_CODE_TO_STATUS[$this->code] ?? null;
        if (! $status) return;

        // عدّلي التواريخ حسب الحالة
        $patch = ['status' => $status];

        if (in_array($status, [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_OUT_FOR_DELIVERY], true)) {
            if (is_null($shipment->shipped_at)) {
                $patch['shipped_at'] = $this->happened_at ?? now();
            }
        }

        if ($status === Shipment::STATUS_DELIVERED) {
            $patch['delivered_at'] = $this->happened_at ?? now();
        }
           // ✅ لو فشل/إرجاع… خزّلي السبب في الشحنة نفسها
    if (in_array($status, [Shipment::STATUS_FAILED, Shipment::STATUS_RETURNED], true)) {
        $patch['failure_reason'] = $this->description; // ممكن تكون null لو بدّلتي القرار لاحقًا
    }

        $shipment->fill($patch)->save();

        // لو عندك هالميثود مضافة بموديل Shipment (تحديث Fulfillment الطلب)
        if (method_exists($shipment, 'refreshOrderFulfillment')) {
            $shipment->refreshOrderFulfillment();
        }
    }

    protected static function booted(): void
    {
        // تحقّق من الكود
        static::creating(function (ShipmentEvent $e) {
            if ($e->code && !in_array($e->code, self::ALLOWED_CODES, true)) {
                throw new \InvalidArgumentException('Invalid shipment event code.');
            }
        });

        // بعد الحفظ/الحذف حدّث حالة الشحنة والطلب
        static::saved(function (ShipmentEvent $e)   { $e->applyToShipment(); });
        static::deleted(function (ShipmentEvent $e) { $e->shipment?->refreshOrderFulfillment(); });
    }
}
// event(new \App\Events\ShipmentStatusChanged(
//     shipment: $e->shipment()->with('order')->first(),
//     eventCode: $e->code
// ));
// app/Models/ShipmentEvent.php داخل booted()->saved
// event(new \App\Events\ShipmentStatusChanged(
//     shipment: $e->shipment()->with('order')->first(),
//     eventCode: $e->code
// ));
