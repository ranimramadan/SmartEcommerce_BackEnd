<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wishlist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['wishlist_id','product_id','product_variant_id'], 'wl_item_unique');
            


        });
    }
    public function down(): void { Schema::dropIfExists('wishlist_items'); }
};
