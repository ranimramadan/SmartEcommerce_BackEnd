<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $t->string('code')->nullable();       // in_transit, hub_scan, out_for_delivery...
            $t->string('description')->nullable();
            $t->string('location')->nullable();
            $t->timestamp('happened_at')->useCurrent();
            $t->timestamps();
            $t->index(['shipment_id','happened_at']);
            $t->index('code', 'shpe_code_idx');
            
        });
    }
    public function down(): void { Schema::dropIfExists('shipment_events'); }
};
