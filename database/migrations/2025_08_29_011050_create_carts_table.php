<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // هوية المالك (مستخدم أو ضيف عبر session)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable()->index();

            // كوبون (اختياري)
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            // مجاميع أساسية
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('subtotal',        12, 2)->default(0);
            $table->decimal('discount_total',  12, 2)->default(0);
            $table->decimal('shipping_total',  12, 2)->default(0);
            $table->decimal('tax_total',       12, 2)->default(0);
            $table->decimal('grand_total',     12, 2)->default(0);

            $table->char('currency', 3)->default('USD');
            $table->enum('status', ['active','converted','abandoned'])->default('active');

            // انتهاء صلاحية سلة الضيف
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();

            // فهرس مفيد للاستعلامات الإدارية
            $table->index(['user_id','status','created_at'], 'carts_user_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
