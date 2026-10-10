<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['telegram', 'whatsapp']);
            $table->string('account_name');
            $table->string('phone_number');
            $table->text('session_data')->nullable();
            $table->enum('status', ['active', 'restricted', 'disconnected'])->default('disconnected');
            $table->unsignedInteger('messages_sent_today')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->timestamps();

            // Same number can be logged in on both platforms, but only once each.
            $table->unique(['platform', 'phone_number'], 'accounts_platform_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
