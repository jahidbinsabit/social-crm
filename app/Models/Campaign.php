<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'platform',
        'message_payload',
        'status',
        'created_by',
        'staff_ids',
        'total_leads',
        'contacted_count',
        'failed_count',
        'pending_count',
    ];

    protected $casts = [
        'staff_ids' => 'array',
        'total_leads' => 'integer',
        'contacted_count' => 'integer',
        'failed_count' => 'integer',
        'pending_count' => 'integer',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(CampaignLead::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

