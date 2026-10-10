<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_delay_seconds')->default(20);
            $table->unsignedInteger('max_delay_seconds')->default(45);
            $table->unsignedInteger('daily_limit_per_account')->default(25);
            $table->string('proxy_ip')->nullable();
            $table->unsignedInteger('proxy_port')->nullable();
            $table->string('proxy_username')->nullable();
            $table->string('proxy_password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
