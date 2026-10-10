<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['telegram', 'whatsapp']);
            $table->string('identifier');
            $table->enum('status', ['pending', 'processing', 'contacted', 'failed'])->default('pending');
            $table->foreignId('assigned_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('fail_reason')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
