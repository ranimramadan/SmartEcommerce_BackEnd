<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderStatusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'note',
        'happened_at',
        'changed_by_id',      // اختياري
        // 'previous_status',  // اختياري
    ];

    protected $casts = [
        'happened_at' => 'datetime',
    ];

    // علاقات
    public function order()        { return $this->belongsTo(Order::class); }
    public function changedBy()    { return $this->belongsTo(User::class, 'changed_by_id'); } // اختياري

    // سكوبات
    public function scopeLatestByEvent($q) { return $q->orderByDesc('happened_at'); }
    public function scopeByStatus($q, $s)  { return $q->where('status', $s); }

    // أدوات
    public static function recordEvent(int $orderId, string $status, ?string $note = null, ?int $changedById = null)
    {
        return static::create([
            'order_id'      => $orderId,
            'status'        => $status,
            'note'          => $note,
            'happened_at'   => now(),
            'changed_by_id' => $changedById,
        ]);
    }

    // مساعدين
    public function isDelivered() { return $this->status === 'delivered'; }
    public function isCancelled() { return $this->status === 'cancelled'; }
    public function isReturned()  { return $this->status === 'returned'; }
    public function isTerminal()  { return in_array($this->status, ['delivered','cancelled','returned'], true); }
}
