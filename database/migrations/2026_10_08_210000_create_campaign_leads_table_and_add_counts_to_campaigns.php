<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('total_leads')->default(0)->after('staff_ids');
            $table->unsignedInteger('contacted_count')->default(0)->after('total_leads');
            $table->unsignedInteger('failed_count')->default(0)->after('contacted_count');
            $table->unsignedInteger('pending_count')->default(0)->after('failed_count');
        });

        Schema::create('campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('identifier');
            $table->enum('platform', ['telegram', 'whatsapp']);
            $table->enum('status', ['pending', 'processing', 'contacted', 'failed'])->default('pending');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('fail_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'identifier']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_leads');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['total_leads', 'contacted_count', 'failed_count', 'pending_count']);
        });
    }
};
