<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_status_events', function (Blueprint $t) {
    $t->id();
    $t->foreignId('order_id')->constrained()->cascadeOnDelete();

    $t->enum('status', ['placed','accepted','processing','on_the_way','delivered','cancelled','returned']);
    // اختياري: لتوثيق الحالة السابقة
    // $t->enum('previous_status', ['placed','accepted','processing','on_the_way','delivered','cancelled','returned'])->nullable();

    $t->string('note')->nullable();
    $t->timestamp('happened_at')->useCurrent();

    // اختياري: مين غيّر الحالة (User/Admin)
    $t->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();

    $t->timestamps();

    // فهارس مهمة
    $t->index(['order_id','happened_at'], 'ose_order_time_idx');
    $t->index('status', 'ose_status_idx');
});

    }
    public function down(): void { Schema::dropIfExists('order_status_events'); }
};
