<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same phone number legitimately belongs to both a WhatsApp and a
     * Telegram account, so uniqueness must be scoped to the platform.
     */
    public function up(): void
    {
        $indexes = DB::select("SHOW INDEXES FROM accounts WHERE Key_name = 'accounts_phone_number_unique'");
        if (!empty($indexes)) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropUnique('accounts_phone_number_unique');
            });
        }

        $platformPhoneIndexes = DB::select("SHOW INDEXES FROM accounts WHERE Key_name = 'accounts_platform_phone_unique'");
        if (empty($platformPhoneIndexes)) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->unique(['platform', 'phone_number'], 'accounts_platform_phone_unique');
            });
        }
    }

    public function down(): void
    {
        $platformPhoneIndexes = DB::select("SHOW INDEXES FROM accounts WHERE Key_name = 'accounts_platform_phone_unique'");
        if (!empty($platformPhoneIndexes)) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropUnique('accounts_platform_phone_unique');
            });
        }
    }
};
