<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // بنود الفاتورة: ترتبط ببنود الطلب، وتسمح بكمية جزئية
    public function up(): void {
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();

            // الفاتورة الأم
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // الربط ببند الطلب (حتى نعرف من أي بند تم فوترة الكمية)
            $t->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            // لقطة اسم المنتج والسعر وقت الفوترة (snapshot)
            $t->string('product_name');
            $t->decimal('unit_price', 12, 2);

            // الكمية المفوترة جزئياً (>=1)
            $t->unsignedInteger('qty');

            // حسومات/ضرائب على مستوى السطر (اختياري)
            $t->decimal('discount_amount', 12, 2)->default(0);
            $t->decimal('tax_amount',      12, 2)->default(0);

            // المجموع الصافي لهذا السطر
            $t->decimal('line_total', 12, 2)->default(0);

            $t->timestamps();

            // نفس order_item ما بينعاد داخل نفس الفاتورة
            $t->unique(['invoice_id', 'order_item_id'], 'invitem_invoice_orderitem_unique');
            $t->index('order_item_id', 'invitem_orderitem_idx');
            
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_items');
    }
};
