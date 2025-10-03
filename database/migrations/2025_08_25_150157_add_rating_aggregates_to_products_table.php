<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('reviews_count')->default(0)->after('price');
            $t->decimal('average_rating', 3, 2)->default(0)->after('reviews_count');
            $t->unsignedInteger('star_1_count')->default(0);
            $t->unsignedInteger('star_2_count')->default(0);
            $t->unsignedInteger('star_3_count')->default(0);
            $t->unsignedInteger('star_4_count')->default(0);
            $t->unsignedInteger('star_5_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn([
                'reviews_count','average_rating',
                'star_1_count','star_2_count','star_3_count','star_4_count','star_5_count',
            ]);
        });
    }
};
