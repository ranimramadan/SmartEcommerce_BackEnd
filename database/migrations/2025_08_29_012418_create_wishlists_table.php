<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::create('wishlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('session_id', 64)->nullable();  
            $t->uuid('share_token')->unique(); // رابط مشاركة
            $t->timestamps();
            $t->index('user_id', 'wish_user_idx');
                $t->index('session_id', 'wish_session_idx');          
    $t->unique(['session_id'], 'wish_session_unique');  
        });
    }
    public function down(): void { Schema::dropIfExists('wishlists'); }
};

