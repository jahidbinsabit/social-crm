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

        DB::statement('DELETE FROM clients WHERE campaign_id IS NULL');
        DB::statement('ALTER TABLE clients MODIFY campaign_id BIGINT UNSIGNED NOT NULL');
    }
};
