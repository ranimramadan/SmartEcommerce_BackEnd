<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group')->default('general');      // general, payment, ui, shipping, i18n ...
            $t->string('key');                             // site_name, supported_locales, ...
            $t->json('value')->nullable();                 // مرن (JSON)
            $t->boolean('is_public')->default(false);      // يظهر للفرونت؟
            $t->timestamps();
            $t->unique(['group','key']);
            $t->index('is_public', 'settings_is_public_idx');

        });
    }
    public function down(): void { Schema::dropIfExists('settings'); }
};
