<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders','coupon_id')) {
                $t->foreignId('coupon_id')->nullable()->after('user_id')
                  ->constrained('coupons')->nullOnDelete();
            }

            if (!Schema::hasColumn('orders','coupon_code'))          $t->string('coupon_code', 50)->nullable()->after('coupon_id');
            if (!Schema::hasColumn('orders','coupon_type'))          $t->enum('coupon_type',['percent','amount','free_shipping'])->nullable()->after('coupon_code');
            if (!Schema::hasColumn('orders','coupon_value'))         $t->decimal('coupon_value', 12, 2)->nullable()->after('coupon_type');
            if (!Schema::hasColumn('orders','coupon_max_discount'))  $t->decimal('coupon_max_discount', 12, 2)->nullable()->after('coupon_value');
            if (!Schema::hasColumn('orders','coupon_free_shipping')) $t->boolean('coupon_free_shipping')->default(false)->after('coupon_max_discount');

            // الخصم النهائي المطبق على الطلب
            if (!Schema::hasColumn('orders','coupon_discount'))      $t->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_free_shipping');
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $t) {
            if (Schema::hasColumn('orders','coupon_discount'))      $t->dropColumn('coupon_discount');
            if (Schema::hasColumn('orders','coupon_free_shipping')) $t->dropColumn('coupon_free_shipping');
            if (Schema::hasColumn('orders','coupon_max_discount'))  $t->dropColumn('coupon_max_discount');
            if (Schema::hasColumn('orders','coupon_value'))         $t->dropColumn('coupon_value');
            if (Schema::hasColumn('orders','coupon_type'))          $t->dropColumn('coupon_type');
            if (Schema::hasColumn('orders','coupon_code'))          $t->dropColumn('coupon_code');

            if (Schema::hasColumn('orders','coupon_id')) {
                $t->dropForeign(['coupon_id']);
                $t->dropColumn('coupon_id');
            }
        });
    }
};
