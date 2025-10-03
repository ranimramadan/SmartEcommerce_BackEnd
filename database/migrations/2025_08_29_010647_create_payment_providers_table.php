<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payment_providers', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();     // stripe, paypal, cod ...
            $t->string('name');
            $t->enum('type', ['online','offline']); // online=بوابة، offline=COD
            $t->json('config')->nullable();   // مفاتيح API/وضع الاختبار...
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['is_active','sort_order'], 'pp_active_sort_idx');
            $t->index('type', 'pp_type_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('payment_providers'); }
};
