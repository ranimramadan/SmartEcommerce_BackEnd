<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();         // SKU لكل تركيبة
            $table->decimal('price', 10, 2)->nullable(); // لو null يستخدم سعر المنتج الأساسي
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('product_variants');
    }
};
