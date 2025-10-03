<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
 Schema::create('order_addresses', function (Blueprint $t) {
    $t->id();
    $t->foreignId('order_id')->constrained()->cascadeOnDelete();
    $t->enum('type', ['billing','shipping']);

    $t->string('first_name', 100);
    $t->string('last_name', 100);
    $t->string('company', 150)->nullable();
    $t->string('country', 2)->nullable();
    $t->string('state', 100)->nullable();
    $t->string('city', 100)->nullable();
    $t->string('zip', 20)->nullable();
    $t->string('address1', 190)->nullable();
    $t->string('address2', 190)->nullable();
    $t->string('phone', 30)->nullable();
    $t->string('email', 190)->nullable();

    $t->timestamps();

    // امنعي التكرار (واحد billing وواحد shipping لكل order)
    $t->unique(['order_id','type'], 'order_address_order_type_unique');
});
    }
    public function down(): void { Schema::dropIfExists('order_addresses'); }
};
