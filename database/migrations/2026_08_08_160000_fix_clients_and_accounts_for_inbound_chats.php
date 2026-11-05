<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Inbound chats (organic messages arriving via the engine webhooks) create
     * clients that do not belong to any campaign, and WhatsApp accounts pass
     * through a 'connecting' state while the QR code is being scanned.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE clients MODIFY campaign_id BIGINT UNSIGNED NULL');

        DB::statement("ALTER TABLE accounts MODIFY status ENUM('active','connecting','restricted','disconnected') NOT NULL DEFAULT 'disconnected'");
    }

    public function down(): void
    {
        DB::statement("UPDATE accounts SET status = 'disconnected' WHERE status = 'connecting'");
        DB::statement("ALTER TABLE accounts MODIFY status ENUM('active','restricted','disconnected') NOT NULL DEFAULT 'disconnected'");

        // PERFECT-FIX #33: destructive DELETE removed — rollback must never delete client data.
        // (Previously deleted every inbound client on rollback.)
        DB::statement('ALTER TABLE clients MODIFY campaign_id BIGINT UNSIGNED NOT NULL');
    }
};
