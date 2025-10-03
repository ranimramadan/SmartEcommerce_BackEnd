<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->nullOnDelete(); // عند اختيار من قائمة
            $table->string('value_text')->nullable(); // عند إدخال نص/رقم حر (مثلاً عدد الصفحات)
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('product_attribute_values');
    }
};
