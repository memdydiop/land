<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class PlatformAuditLog extends Model
{
    use HasUlids;

    protected $table = 'platform_audit_logs';

    protected $fillable = [
        'event',
        'ip_address',
        'user_agent',
        'actor_id',
        'actor_name',
        'actor_email',
        'tenant_id',
        'tenant_name',
        'tenant_slug',
        'status',
        'failure_code',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
