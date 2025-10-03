<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shipping_carrier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('tracking_number', 100)->nullable();

            $t->enum('status', [
                'label_created','in_transit','out_for_delivery','delivered','failed','returned'
            ])->default('label_created');

            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();

            // ✅ سبب آخر حالة فشل/إرجاع (اختياري)
            $t->text('failure_reason')->nullable();

            $t->timestamps();

            // فهارس مهمّة للاستعلامات
            $t->index(['order_id','status'], 'shp_order_status_idx');
            $t->index('shipping_carrier_id', 'shp_carrier_idx');

            // منع تكرار رقم تتبّع عند نفس الناقل (يسمح بعدة NULL)
            $t->unique(['shipping_carrier_id','tracking_number'], 'shp_carrier_tracking_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shipments');
    }
};
