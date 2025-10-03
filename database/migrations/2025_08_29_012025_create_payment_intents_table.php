<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
Schema::create('payment_intents', function (Blueprint $t) {
    $t->id();

    $t->foreignId('order_id')->constrained()->cascadeOnDelete();
    $t->foreignId('payment_provider_id')->nullable()->constrained()->nullOnDelete();

    $t->string('provider_payment_id')->nullable();  // stripe_pi_id / paypal_id ...
    $t->string('client_secret')->nullable();        // لِـ Stripe
    $t->string('idempotency_key', 100)->nullable(); // السلامة من التكرار

    $t->enum('status', [
        'requires_payment_method','requires_confirmation','processing',
        'succeeded','canceled','failed'
    ])->default('requires_payment_method');

    $t->decimal('amount', 12, 2)->default(0);
    $t->string('currency',3)->default('USD');
    $t->json('meta')->nullable();

    $t->timestamps();

    // ========= Indexes & Uniques =========
    $t->index('order_id', 'pi_order_idx');
    $t->index(['payment_provider_id','status'], 'pi_provider_status_idx');
    $t->index('created_at', 'pi_created_idx');

    // منع تكرار نفس intent عند نفس المزوّد
    $t->unique(['payment_provider_id','provider_payment_id'], 'pi_provider_pid_unique');

    // Idempotency (حتى لو رجع الريكوست/الويبهوك مرتين)
    $t->unique('idempotency_key', 'pi_idem_uniq');
});

    }
    public function down(): void { Schema::dropIfExists('payment_intents'); }
};
