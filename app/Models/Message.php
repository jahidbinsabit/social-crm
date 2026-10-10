<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'platform',
        'account_id',
        'client_id',
        'staff_id',
        'sender_type',
        'message_type',
        'message_body',
        'media_path',
        'media_mime',
        'media_name',
        'media_size',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'media_size' => 'integer',
    ];

    /** Expose the browser-facing media URL everywhere the model is serialised. */
    protected $appends = ['media_url'];

    public function getMediaUrlAttribute(): ?string
    {
        if (!$this->media_path) return null;

        // Manually build URL to preserve slashes in the path segment.
        // route() encodes slashes to %2F which breaks the browser request.
        return url('/media/' . ltrim($this->media_path, '/'));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
