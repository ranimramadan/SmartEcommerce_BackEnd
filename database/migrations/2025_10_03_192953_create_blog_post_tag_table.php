<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blog_post_tag', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $t->primary(['post_id', 'tag_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('blog_post_tag');
    }
};
