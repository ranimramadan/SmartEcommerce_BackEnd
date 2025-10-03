<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
      Schema::create('shipment_items', function (Blueprint $t) {
    $t->id();
    $t->foreignId('shipment_id')->constrained()->cascadeOnDelete();
    $t->foreignId('order_item_id')->constrained()->restrictOnDelete();
    $t->unsignedInteger('qty')->default(1);
    $t->timestamps();

    // نفس بند الطلب ما يتكرر داخل نفس الشحنة
    $t->unique(['shipment_id','order_item_id'], 'shi_shipment_orderitem_unique');

    // فهارس مساعدة للاستعلامات
    $t->index('shipment_id',    'shi_shipment_idx');
    $t->index('order_item_id',  'shi_orderitem_idx');
});

    }
    public function down(): void { Schema::dropIfExists('shipment_items'); }
};
