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

    /**
     * Never serialize the proxy password into views, JSON or broadcasts.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'proxy_password',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest. NOTE for deploy: a plaintext value saved
            // before this cast existed will not decrypt — re-enter the
            // proxy password once after shipping.
            'proxy_password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'min_delay_seconds' => 20,
            'max_delay_seconds' => 45,
            'daily_limit_per_account' => 25,
        ]);
    }
}
