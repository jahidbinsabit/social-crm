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

    protected function casts(): array
    {
        return [
            'last_reset_date' => 'date',
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
