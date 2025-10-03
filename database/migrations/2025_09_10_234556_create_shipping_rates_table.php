<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipping_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            // اختياري: ربط بمزوّد شحن معيّن (لو عندك جدول shipping_carriers)
            $t->foreignId('carrier_id')->nullable()->constrained('shipping_carriers')->nullOnDelete();

            $t->string('name', 150);        // اسم الخدمة الظاهر للزبون: "Standard", "Express"
            $t->string('code', 100)->nullable(); // كود داخلي أو كود API
            $t->boolean('is_active')->default(true);

            // التسعير
            $t->decimal('price', 12, 2)->default(0);       // ثابت
            $t->decimal('per_kg', 12, 2)->default(0);      // لكل كغ (لو الوزن متوفر)
            $t->decimal('per_item', 12, 2)->default(0);    // لكل قطعة
            $t->decimal('free_over', 12, 2)->nullable();   // شحن مجاني فوق هذا المبلغ

            // شروط تطبيق السعر (أي شرط غير ممرّر = نتجاهله)
            $t->decimal('min_subtotal', 12, 2)->nullable();
            $t->decimal('max_subtotal', 12, 2)->nullable();
            $t->decimal('min_weight', 12, 3)->nullable();
            $t->decimal('max_weight', 12, 3)->nullable();
            $t->unsignedInteger('min_qty')->nullable();
            $t->unsignedInteger('max_qty')->nullable();

            // زمن التوصيل التقريبي
            $t->unsignedSmallInteger('eta_days_min')->nullable();
            $t->unsignedSmallInteger('eta_days_max')->nullable();

            // عملة السعر (لو null = عملة السلة/الطلب)
            $t->char('currency', 3)->nullable();

            $t->unsignedInteger('sort_order')->default(0);

            // بيانات إضافية للمزوّد (توكن، سيرفس كود، الخ)
            $t->json('data')->nullable();

            $t->timestamps();

            $t->index(['zone_id', 'is_active', 'sort_order'], 'rates_zone_active_sort_idx');
            $t->index(['carrier_id'], 'rates_carrier_idx');
            $t->index(['min_subtotal', 'max_subtotal'], 'rates_subtotal_range_idx');
            $t->index(['min_weight', 'max_weight'], 'rates_weight_range_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shipping_rates');
    }
};
