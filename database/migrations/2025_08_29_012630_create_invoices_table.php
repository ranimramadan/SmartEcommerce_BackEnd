<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // فواتير مرتبطة بالطلب، وتدعم أكثر من فاتورة لنفس الطلب
    public function up(): void {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();

            // الفاتورة تخص طلب معيّن
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();

            // رقم فاتورة فريد (املئيه من السيرفس - تسلسل/سنة/بادئة...)
            $t->string('invoice_no', 50)->unique();

            // draft/issued/paid/void/refunded (اختاري ما يناسبك)
            $t->enum('status', ['draft','issued','paid','void','refunded'])->default('issued');

            // عملة ومجاميع
            $t->char('currency', 3)->default('USD');
            $t->decimal('subtotal',       12, 2)->default(0);
            $t->decimal('discount_total', 12, 2)->default(0);
            $t->decimal('tax_total',      12, 2)->default(0);
            $t->decimal('shipping_total', 12, 2)->default(0);
            $t->decimal('grand_total',    12, 2)->default(0);

            // تواريخ وبيانات إضافية
            $t->timestamp('issued_at')->nullable(); // وقت إصدار الفاتورة
            $t->timestamp('due_at')->nullable();    // تاريخ الاستحقاق (إن وجد)
            $t->timestamp('paid_at')->nullable();   // عند السداد
            $t->string('pdf_path')->nullable();     // مسار ملف PDF (اختياري)
            $t->text('notes')->nullable();

            $t->timestamps();
            
            $t->index(['order_id','status'], 'inv_order_status_idx');
            $t->index('created_at', 'inv_created_idx'); // مفيدة للتقارير الشهرية

            
            

        });
    }

    public function down(): void {
        Schema::dropIfExists('invoices');
    }
};
