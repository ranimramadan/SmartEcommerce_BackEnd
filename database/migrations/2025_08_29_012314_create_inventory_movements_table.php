<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('change'); // +10 أو -3
            $t->enum('reason', ['order_reserved','order_cancelled','order_shipped','manual_adjustment']);
            $t->string('reference_type')->nullable();       // morph alias أو FQCN
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // من قام بالتعديل

            // أعمدة التدقيق (قبل/بعد + ملاحظة)
            $t->integer('stock_before')->nullable();
            $t->integer('stock_after')->nullable();
            $t->string('note', 500)->nullable();

            $t->timestamps();

            // فهارس
            $t->index(['product_id','product_variant_id']);
            $t->index(['reference_type','reference_id'], 'inv_ref_idx');
            $t->index('reason', 'inv_reason_idx');
            $t->index('created_at', 'inv_created_idx');
            $t->index(['product_id','product_variant_id','created_at'], 'inv_prod_time_idx');
        });

        // قيد CHECK (اختياري) — بعض محركات/إصدارات MySQL القديمة تتجاهله:
        try {
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT chk_change_nonzero CHECK (change <> 0)');
        } catch (\Throwable $e) {
            // تجاهل لو غير مدعوم
        }
    }

    public function down(): void {
        Schema::dropIfExists('inventory_movements');
    }
};
