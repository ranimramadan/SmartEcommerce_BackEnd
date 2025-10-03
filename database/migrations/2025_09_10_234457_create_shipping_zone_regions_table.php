<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipping_zone_regions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();

            // مطابقة الوجهة: أي من هذه الحقول يُستخدم للتطابق (OR داخل نفس السطر)
            // خليه بسيط: country (ISO2)، state/region كود أو نص قصير، و postal pattern (مثل "10*" أو Regex لاحقاً)
            $t->char('country', 2)->nullable();
            $t->string('state', 50)->nullable();
            $t->string('postal_pattern', 50)->nullable(); // يدعم البدائل البسيطة: 10*, 1234?, إلخ

            // include/exclude rule (لتعمل بلاكلِست على نفس الزون)
            $t->enum('rule', ['include', 'exclude'])->default('include');

            $t->timestamps();

            $t->index(['zone_id', 'country', 'state'], 'zone_region_match_idx');
            $t->index(['zone_id', 'rule'], 'zone_region_rule_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shipping_zone_regions');
    }
};
