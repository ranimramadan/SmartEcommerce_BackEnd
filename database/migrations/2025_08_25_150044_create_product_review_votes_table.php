<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_review_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->boolean('is_helpful'); // true: مفيد
            $t->timestamps();

            $t->unique(['review_id','user_id']); // تصويت واحد لكل مستخدم
            $t->index(['review_id','is_helpful']);
            $t->index(['review_id','user_id'], 'prv_review_user_idx');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_votes');
    }
};
