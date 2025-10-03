<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blog_tags', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 150)->unique();
            $t->unsignedInteger('posts_count')->default(0);
            $t->timestamps();

            $t->index('slug');
        });
    }
    public function down(): void {
        Schema::dropIfExists('blog_tags');
    }
};
