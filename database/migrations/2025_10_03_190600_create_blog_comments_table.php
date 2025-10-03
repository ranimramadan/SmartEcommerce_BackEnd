<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blog_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('author_name', 120)->nullable();  // للضيف
            $t->string('author_email', 190)->nullable(); // للضيف
            $t->text('body');

            $t->enum('status', ['pending','approved','rejected'])->default('pending');
            $t->timestamps();

            $t->index(['post_id', 'status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('blog_comments');
    }
};
