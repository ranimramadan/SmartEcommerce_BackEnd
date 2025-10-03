<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();

            $t->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();

            // المستخدم ممكن يكون مجهول قبل اللوجين (خليه nullable عندك إذا محتاجة)
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // استخدام قبل الشراء (Cart) أو على طلب فعلي (Order)
            // ملاحظة: تأكدي إن جدول carts موجود قبلها، أو عدّلي اسم الجدول لو اختلف
            $t->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // الخصم الفعلي المُطبّق عند لحظة الاستخدام
            $t->decimal('amount', 12, 2)->default(0);

            // وقت الاستخدام (يفيد بفلترة السجل)
            $t->timestamp('used_at')->nullable();

            $t->timestamps();

            // منع تكرار نفس الكوبون لنفس الطلب/السلة
            $t->unique(['coupon_id','order_id'], 'coupon_redemption_order_unique');
            $t->unique(['coupon_id','cart_id'],  'coupon_redemption_cart_unique');

            // للاستعلام حسب المستخدم/الكوبون بكفاءة
            $t->index(['coupon_id', 'user_id'], 'coupon_user_idx');

            
            $t->index(['coupon_id','user_id','created_at'], 'cr_coupon_user_created_idx');
            $t->index('order_id', 'cr_order_idx');
            $t->index('cart_id',  'cr_cart_idx');
            $t->index('used_at',  'cr_used_idx'); // تقارير حسب التاريخ

        });
    }

    public function down(): void {
        Schema::dropIfExists('coupon_redemptions');
    }
};
