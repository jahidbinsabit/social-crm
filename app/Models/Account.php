<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'platform',
        'account_name',
        'phone_number',
        'session_data',
        'status',
        'messages_sent_today',
        'last_reset_date',
    ];

    /**
     * Attributes that must never be serialized (arrays, JSON, broadcasts).
     * session_data holds live WhatsApp/Telegram session credentials.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'session_data',
    ];

    protected function casts(): array
    {
        return [
            'last_reset_date' => 'date',
            // Encrypted at rest: a database leak must not hand over live
            // WA/TG sessions. NOTE for deploy: values written before this
            // cast existed are plaintext and will not decrypt — accounts
            // need a one-off re-encrypt (or reconnect) when this ships.
            'session_data' => 'encrypted',
        ];
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_staff');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_account_id');
    }
}
