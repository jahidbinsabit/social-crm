<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages can now carry an image, video, audio clip, or document in
     * addition to (or instead of) text — matching what WhatsApp and Telegram
     * natively support.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // 'text' keeps every existing row valid without a data backfill.
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'document', 'sticker'])
                ->default('text')
                ->after('sender_type');

            $table->string('media_path')->nullable()->after('message_body');
            $table->string('media_mime', 128)->nullable()->after('media_path');
            $table->string('media_name')->nullable()->after('media_mime');
            $table->unsignedBigInteger('media_size')->nullable()->after('media_name');
        });

        // A media-only message has no caption, so the body must be optional.
        DB::statement('ALTER TABLE messages MODIFY message_body TEXT NULL');
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'message_type',
                'media_path',
                'media_mime',
                'media_name',
                'media_size',
            ]);
        });

        DB::statement("UPDATE messages SET message_body = '' WHERE message_body IS NULL");
        DB::statement('ALTER TABLE messages MODIFY message_body TEXT NOT NULL');
    }
};
