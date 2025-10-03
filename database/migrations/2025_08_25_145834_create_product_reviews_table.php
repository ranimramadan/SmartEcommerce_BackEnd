<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id();

            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $t->tinyInteger('rating'); // 1..5
            $t->string('title')->nullable();
            $t->text('body')->nullable();

            // سياسة العرض
            $t->enum('status', ['approved','pending','rejected'])->default('pending');
            $t->boolean('is_verified')->default(false);
            $t->boolean('has_media')->default(false);

            // حقول moderation (تفيد للإدارة والشفافية)
            $t->foreignId('moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('moderated_at')->nullable();
            $t->text('admin_note')->nullable();

            // عداد التبليغات (ملخّص سريع)
            $t->unsignedInteger('reported_count')->default(0);

            $t->timestamps();

            // قيود وأداء
            $t->unique(['product_id','user_id']);         // مراجعة واحدة لكل مستخدم/منتج
            $t->index(['product_id','status','rating']);   // فلاتر سريعة
            
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
