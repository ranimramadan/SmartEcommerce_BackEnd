<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_review_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $t->string('reason', 30); // abuse|spam|off_topic|privacy|other
            $t->text('note')->nullable();

            $t->timestamps();

            $t->unique(['review_id','user_id']); // بلاغ واحد لكل مستخدم على نفس المراجعة
            $t->index(['reason','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_reports');
    }
};
