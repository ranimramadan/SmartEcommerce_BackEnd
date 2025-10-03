<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // المتغير (إن وُجد)
            $table->unsignedBigInteger('product_variant_id')->nullable();

            // سنابشوت بيانات العنصر لحظة الإضافة
            $table->string('sku', 100)->nullable();
            $table->string('name', 255)->nullable();

            // أسعار/مجاميع (استخدم decimal فقط — لا يوجد unsignedDecimal)
            $table->decimal('price',         12, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);

            $table->decimal('line_subtotal', 12, 2)->default(0); // price * qty
            $table->decimal('line_discount', 12, 2)->default(0);
            $table->decimal('line_total',    12, 2)->default(0); // line_subtotal - line_discount

            $table->json('options')->nullable();

            $table->timestamps();

            // فهارس
            $table->index(['cart_id','product_id','product_variant_id'], 'cart_items_product_idx');

            // FK للمتغير إن وُجد
            $table->foreign('product_variant_id')
                  ->references('id')->on('product_variants')
                  ->nullOnDelete();

            /**
             * منع تكرار السطر لنفس (cart_id, product_id, product_variant_id)
             * حتى لو كانت product_variant_id = NULL.
             * نستخدم عمود مُولّد يحوّل NULL إلى 0 ثم فهرس فريد.
             */
            $table->unsignedBigInteger('variant_id_nz')->storedAs('COALESCE(product_variant_id, 0)');
            $table->unique(['cart_id','product_id','variant_id_nz'], 'cart_item_unique2');
        });

        // (اختياري) قيد تحقق للكمية والقيم غير السالبة (لو DB يدعم CHECK)
        // DB::statement('ALTER TABLE cart_items ADD CONSTRAINT chk_cart_items_nonneg CHECK (price >= 0 AND line_subtotal >= 0 AND line_discount >= 0 AND line_total >= 0 AND qty >= 1)');
    }

    public function down(): void
    {
        // إسقاط الفريد قبل حذف الجدول (لتجنب مشاكل بعض الإعدادات)
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                // أسقطي الفريد إذا كان موجود
                try { $table->dropUnique('cart_item_unique2'); } catch (\Throwable $e) {}
            });
        }

        Schema::dropIfExists('cart_items');
    }
};
