<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blog_post_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $t->string('path');                 // storage path
            $t->string('alt')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['post_id', 'sort_order']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('blog_post_images');
    }
};
