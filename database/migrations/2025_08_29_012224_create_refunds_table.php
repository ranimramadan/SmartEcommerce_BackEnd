<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
     Schema::create('refunds', function (Blueprint $t) {
    $t->id();

    $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
    $t->foreignId('order_id')->constrained()->cascadeOnDelete();

    $t->decimal('amount', 12, 2)->default(0);
    $t->enum('status', ['pending','succeeded','failed'])->default('pending');

    $t->string('reason')->nullable();

    // مُعرّف الاسترداد عند مزوّد الدفع
    $t->string('provider_refund_id')->nullable();

    // لمنع التكرار عبر retries / webhooks
    $t->string('idempotency_key', 100)->nullable();

    // (اختياري) لتخزين الاستجابة كاملة
    $t->json('meta')->nullable();

    $t->timestamps();

    // ========= Indexes =========
    $t->index('payment_id', 'refunds_payment_idx');
    $t->index('order_id',   'refunds_order_idx');
    $t->index('status',     'refunds_status_idx');
    $t->index('created_at', 'refunds_created_idx');

    // فريد لمنع نفس الطلب يُسجَّل مرتين
    $t->unique('idempotency_key', 'refunds_idem_uniq');

    // لو بدك تمنعي تكرار provider_refund_id (قد يتكرر عبر مزودات مختلفة، بس ما عندنا provider_id هنا)
    // $t->unique('provider_refund_id', 'refunds_provider_refund_uniq');

    // (اختياري) MySQL 8+ فقط:
    // $t->check('amount >= 0');
});

    }
    public function down(): void { Schema::dropIfExists('refunds'); }
};
