<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique(); // SKU عام اختياري
            $table->decimal('price', 10, 2);            // سعر أساسي
            $table->text('short_description')->nullable();
            $table->longText('long_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('products');
    }
};
