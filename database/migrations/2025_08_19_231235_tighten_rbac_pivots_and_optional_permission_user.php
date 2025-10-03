<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // role_user: UNIQUE(user_id, role_id) إذا ناقص
        if (Schema::hasTable('role_user')) {
            if (! $this->indexExists('role_user', 'ru_user_role_unique')) {
                Schema::table('role_user', function (Blueprint $table) {
                    $table->unique(['user_id','role_id'], 'ru_user_role_unique');
                });
            }
        }

        // permission_role: UNIQUE(role_id, permission_id) إذا ناقص
        if (Schema::hasTable('permission_role')) {
            if (! $this->indexExists('permission_role', 'pr_role_perm_unique')) {
                Schema::table('permission_role', function (Blueprint $table) {
                    $table->unique(['role_id','permission_id'], 'pr_role_perm_unique');
                });
            }
        }

        // users: تأكيد is_active إذا ناقص
        if (Schema::hasTable('users') && ! Schema::hasColumn('users','is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('password');
            });
        }

        // ملحوظة: ما منلمس permission_user هون (إله هجرته المنفصلة)
    }

    public function down(): void
    {
        // ملاحظة مهمة:
        // قبل إسقاط أي UNIQUE مركّب مستخدم من FK، منضيف INDEX عادي يغطي نفس الأعمدة
        // لتضل قيود FK سعيدة.

        // role_user
        if (Schema::hasTable('role_user')) {
            // لو الـ UNIQUE موجود
            if ($this->indexExists('role_user', 'ru_user_role_unique')) {
                // ضيفي فهرس عادي يغطي نفس الأعمدة إذا ناقص
                if (! $this->indexExists('role_user', 'ru_user_role_idx')) {
                    Schema::table('role_user', function (Blueprint $table) {
                        $table->index(['user_id','role_id'], 'ru_user_role_idx');
                    });
                }
                // الآن أسقطي الـ UNIQUE بأمان
                Schema::table('role_user', function (Blueprint $table) {
                    $table->dropUnique('ru_user_role_unique');
                });
            }
        }

        // permission_role
        if (Schema::hasTable('permission_role')) {
            if ($this->indexExists('permission_role', 'pr_role_perm_unique')) {
                if (! $this->indexExists('permission_role', 'pr_role_perm_idx')) {
                    Schema::table('permission_role', function (Blueprint $table) {
                        $table->index(['role_id','permission_id'], 'pr_role_perm_idx');
                    });
                }
                Schema::table('permission_role', function (Blueprint $table) {
                    $table->dropUnique('pr_role_perm_unique');
                });
            }
        }

        // ما منشيل users.is_active في down (اختياري/أكثر أماناً)
    }

    /** فحص وجود فهرس/إندكس باسم محدد (MySQL/MariaDB) */
    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();
        return DB::table('information_schema.statistics')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
