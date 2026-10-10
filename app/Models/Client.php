<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'campaign_id',
        'platform',
        'identifier',
        'display_name',
        'status',
        'assigned_account_id',
        'fail_reason',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
