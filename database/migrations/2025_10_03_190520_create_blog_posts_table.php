<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('title', 220);
            $t->string('slug', 240)->unique();
            $t->string('excerpt', 500)->nullable();
            $t->longText('content')->nullable();

            $t->string('cover_image_path')->nullable();

            $t->boolean('is_published')->default(false);
            $t->timestamp('published_at')->nullable();

            $t->json('meta')->nullable();           // seo_title/description/og_image...
            $t->unsignedBigInteger('view_count')->default(0);

            $t->timestamps();

            $t->index(['category_id', 'is_published']);
            $t->index('published_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('blog_posts');
    }
};
