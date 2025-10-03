<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blog_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $t->string('name', 190);
            $t->string('slug', 200)->unique();
            $t->string('image_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('posts_count')->default(0);
            $t->text('description')->nullable();
            $t->timestamps();

            $t->index('parent_id');
            $t->index('is_active');
        });
    }
    public function down(): void {
        Schema::dropIfExists('blog_categories');
    }
};
