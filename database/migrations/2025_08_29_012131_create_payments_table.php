<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();

            // ربط الدفعة بطلب
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();

            // مزود الدفع (Stripe/PayPal/COD...)
            $t->foreignId('payment_provider_id')->nullable()->constrained()->nullOnDelete();

            // مفتاح منع التكرار (Idempotency) - مهم مع الويبهوكات وإعادة الإرسال
            $t->string('idempotency_key', 100)->nullable();
            $t->unique('idempotency_key', 'payments_idem_uniq');

            // معرف العملية عند المزود
            $t->string('transaction_id')->nullable(); // charge id / capture id

            // حالة الدفع
            $t->enum('status', ['authorized','captured','refunded','failed'])->default('authorized');

            // مبالغ/عملة
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('currency',3)->default('USD');

            // الاستجابة الخام من المزود
            $t->json('raw_response')->nullable();

            $t->timestamps();

            // فهارس للأداء
            $t->index('order_id', 'pay_order_idx');
            $t->index(['payment_provider_id','status'], 'pay_providerid_status_idx');
            $t->index('created_at', 'pay_created_idx');

            // (اختياري) منع تكرار نفس transaction عند نفس المزوّد
            $t->unique(['payment_provider_id','transaction_id'], 'pay_provider_txn_unique');
            
            
            
                $t->index('transaction_id', 'pay_txn_idx'); // اختياري لكن عملي جداً


        });
    }

    public function down(): void { Schema::dropIfExists('payments'); }
};
