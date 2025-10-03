<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();

            $t->string('code', 50)->unique(); // خزّنيه UPPER بالتطبيق
            $t->enum('type', ['percent','amount','free_shipping']);

            // value: للـ percent = 0..100، للـ amount = قيمة بالعملة، للـ free_shipping = NULL أو 0
            $t->decimal('value', 12, 2)->nullable();

            // سقف خصم للنسبة المئوية (اختياري)
            $t->decimal('max_discount', 12, 2)->nullable();

            // شروط السلة
            $t->decimal('min_cart_total', 12, 2)->nullable();
            $t->unsignedInteger('min_items_count')->nullable();

            // الحدود
            $t->unsignedInteger('max_uses')->nullable();             // إجمالي
            $t->unsignedInteger('max_uses_per_user')->nullable();    // لكل مستخدم

            // الصلاحية والتفعيل
            $t->timestamp('start_at')->nullable();
            $t->timestamp('end_at')->nullable();
            $t->boolean('is_active')->default(true);

            $t->timestamps();

            // اندكس للاستعلام حسب الفاعلية والفترة
            $t->index(['is_active','start_at','end_at'], 'coupons_active_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('coupons');
    }
};
