<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipping_zones', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('code', 50)->unique(); // مثلاً: GLOBAL, MENAT, US_ONLY
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['is_active', 'sort_order'], 'zones_active_sort_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shipping_zones');
    }
};
