<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number', 32)->unique();      // مثل SDGT1254FD
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            $t->enum('status', ['placed','accepted','processing','on_the_way','delivered','cancelled','returned'])
              ->default('placed');                         // شريط التتبّع بالسكرينات

            $t->enum('payment_status', ['unpaid','authorized','paid','failed','refunded'])
              ->default('unpaid');
            $t->enum('fulfillment_status', ['unfulfilled','partial','fulfilled'])
              ->default('unfulfilled');

            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount_total', 12, 2)->default(0);
            $t->decimal('shipping_total', 12, 2)->default(0);
            $t->decimal('tax_total', 12, 2)->default(0);
            $t->decimal('grand_total', 12, 2)->default(0);
            $t->string('currency', 3)->default('USD');

            
            

            $t->foreignId('payment_provider_id')->nullable()->constrained()->nullOnDelete(); // Stripe/PayPal/COD...
            $t->timestamps();

            $t->index(['status','user_id','created_at'], 'orders_status_user_created_idx');
            $t->index('payment_status', 'orders_payment_status_idx');
            

        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
