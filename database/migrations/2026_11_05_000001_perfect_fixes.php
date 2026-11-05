<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perfect-fix batch (audit items #7, #13, #14, #19, #33):
     *
     *  #13 clients gain a real account_id so a conversation belongs to ONE
     *      account; (account_id, platform, identifier) becomes the dedup
     *      guard for inbound clients (the old (campaign_id, identifier)
     *      unique cannot dedupe inbound rows because campaign_id is NULL
     *      there and MySQL treats NULLs as distinct).
     *  #14 messages gain external_id (engine message id) with a unique
     *      (account_id, external_id) so engine webhook retries cannot
     *      duplicate a message.
     *  #7  'paused' is added to the clients/campaign_leads status ENUMs so
     *      quota-exhausted leads wait for the next day instead of dying.
     *  #33 campaign_leads (campaign_id, identifier) becomes UNIQUE (the old
     *      index was not), and messages get a composite chat index.
     *
     * Uniques that could collide with pre-existing duplicate rows are only
     * added when the data is already clean; otherwise they are skipped so
     * the migration never half-applies — run `clients:merge-duplicates`
     * first, then re-run this migration's unique steps manually.
     */
    public function up(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        // ── #13: clients.account_id ─────────────────────────────────────
        if (!Schema::hasColumn('clients', 'account_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('campaign_id');
            });
        }

        // Backfill from the legacy assignment before constraining anything.
        DB::statement('UPDATE clients SET account_id = assigned_account_id WHERE account_id IS NULL AND assigned_account_id IS NOT NULL');

        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('account_id', 'clients_account_id_fk')
                ->references('id')->on('accounts')->nullOnDelete();
            $table->index('account_id', 'clients_account_id_idx');
        });

        if (!$this->hasDuplicateClientAccounts()) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique(['account_id', 'platform', 'identifier'], 'clients_account_platform_identifier_unique');
            });
        }

        // ── #14: messages.external_id ───────────────────────────────────
        if (!Schema::hasColumn('messages', 'external_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->string('external_id', 191)->nullable()->after('staff_id');
                // NULL external_ids (all legacy rows) never collide in MySQL.
                $table->unique(['account_id', 'external_id'], 'messages_account_external_unique');
            });
        }

        // ── #7: 'paused' status (ENUMs are a MySQL concept here) ────────
        if ($isMysql) {
            DB::statement("ALTER TABLE clients MODIFY status ENUM('pending','processing','contacted','failed','paused') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE campaign_leads MODIFY status ENUM('pending','processing','contacted','failed','paused') NOT NULL DEFAULT 'pending'");
        }
        // SQLite (tests) stores enums as varchar + check-less column in this
        // project, so 'paused' needs no DDL there.

        // ── #19: deleting an account must not cascade-delete chat history ──
        // messages.account_id: cascade -> nullable + nullOnDelete (MySQL only;
        // the FK swap needs the exact constraint from the create migration).
        if ($isMysql) {
            try {
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropForeign(['account_id']);
                });
                Schema::table('messages', function (Blueprint $table) {
                    $table->unsignedBigInteger('account_id')->nullable()->change();
                });
                Schema::table('messages', function (Blueprint $table) {
                    $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // If the constraint name differs, leave the schema untouched
                // rather than half-applying; fix manually per fix log #19.
                report($e);
            }
        }

        // ── #33: campaign_leads unique + messages composite index ───────
        if (!$this->hasDuplicateLeads()) {
            Schema::table('campaign_leads', function (Blueprint $table) {
                $table->dropIndex(['campaign_id', 'identifier']);
                $table->unique(['campaign_id', 'identifier'], 'campaign_leads_campaign_identifier_unique');
            });
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['account_id', 'client_id', 'created_at'], 'messages_account_client_created_idx');
        });
    }

    public function down(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_account_client_created_idx');
        });

        try {
            Schema::table('campaign_leads', function (Blueprint $table) {
                $table->dropUnique('campaign_leads_campaign_identifier_unique');
                $table->index(['campaign_id', 'identifier']);
            });
        } catch (\Throwable $e) {
            // Unique was skipped in up() (duplicate data) — nothing to undo.
        }

        if ($isMysql) {
            DB::statement("UPDATE clients SET status = 'pending' WHERE status = 'paused'");
            DB::statement("UPDATE campaign_leads SET status = 'pending' WHERE status = 'paused'");
            DB::statement("ALTER TABLE clients MODIFY status ENUM('pending','processing','contacted','failed') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE campaign_leads MODIFY status ENUM('pending','processing','contacted','failed') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn('messages', 'external_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropUnique('messages_account_external_unique');
                $table->dropColumn('external_id');
            });
        }

        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_account_platform_identifier_unique');
            });
        } catch (\Throwable $e) {
            // Unique was skipped in up() (duplicate data) — nothing to undo.
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign('clients_account_id_fk');
            $table->dropIndex('clients_account_id_idx');
            $table->dropColumn('account_id');
        });
    }

    private function hasDuplicateClientAccounts(): bool
    {
        return DB::table('clients')
            ->select('account_id', 'platform', 'identifier')
            ->whereNotNull('account_id')
            ->groupBy('account_id', 'platform', 'identifier')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function hasDuplicateLeads(): bool
    {
        return DB::table('campaign_leads')
            ->select('campaign_id', 'identifier')
            ->groupBy('campaign_id', 'identifier')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};
