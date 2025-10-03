<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_review_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
            $t->string('file_path');
            $t->string('mime_type')->nullable();
            $t->unsignedInteger('size')->nullable(); // bytes
            $t->enum('type', ['image','video'])->default('image');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['review_id','type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_media');
    }
};
