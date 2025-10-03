<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sku')->nullable();
            $t->string('name');
            $t->decimal('price', 12, 2)->default(0);
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('line_subtotal', 12, 2)->default(0);
            $t->decimal('line_discount', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2)->default(0);
            $t->json('options')->nullable();
            $t->timestamps();

            $t->index('order_id', 'oi_order_idx');
            $t->index('product_id', 'oi_product_idx');
            // لو عندك variant_id:
            $t->index('product_variant_id', 'oi_product_variant_id_idx');

        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); }
};
