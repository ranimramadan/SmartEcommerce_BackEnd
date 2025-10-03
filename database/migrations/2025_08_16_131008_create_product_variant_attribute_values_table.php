<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // ✅ اسم قصير للـ UNIQUE لتفادي حد 64 حرف في MySQL
            $table->unique(['product_variant_id','attribute_value_id'], 'pvav_variant_value_uq');

            // (اختياري) فهارس قصيرة للأداء
            $table->index('product_variant_id', 'pvav_variant_idx');
            $table->index('attribute_value_id', 'pvav_value_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('product_variant_attribute_values');
    }
};
