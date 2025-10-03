<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // ربط (اختياري) الدفعة بفاتورة معيّنة
    public function up(): void {
        Schema::table('payments', function (Blueprint $t) {
            if (! Schema::hasColumn('payments', 'invoice_id')) {
                $t->foreignId('invoice_id')
                  ->nullable()
                  ->after('order_id')
                  ->constrained('invoices')
                  ->nullOnDelete();
            }
        });
    }

    public function down(): void {
        Schema::table('payments', function (Blueprint $t) {
            if (Schema::hasColumn('payments', 'invoice_id')) {
                $t->dropForeign(['invoice_id']);
                $t->dropColumn('invoice_id');
            }
        });
    }
};
