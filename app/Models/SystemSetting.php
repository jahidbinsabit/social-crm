<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'min_delay_seconds',
        'max_delay_seconds',
        'daily_limit_per_account',
        'proxy_ip',
        'proxy_port',
        'proxy_username',
        'proxy_password',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'min_delay_seconds' => 20,
            'max_delay_seconds' => 45,
            'daily_limit_per_account' => 25,
        ]);
    }
}
