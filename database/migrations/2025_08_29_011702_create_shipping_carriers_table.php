<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipping_carriers', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();    // internal: internal_fleet, dhl, aramex ...
            $t->string('name');
            $t->string('website')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index('is_active', 'shipping_carriers_active_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('shipping_carriers'); }
};
