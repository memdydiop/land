<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Events;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $table = 'tenants';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * Columns stored directly in public.tenants instead of the JSON data column.
     */
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'slug',
            'status',
            'schema_name',
            'database_identifier',
            'timezone',
            'locale',
            'currency',
            'trial_ends_at',
            'suspended_at',
        ]);
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function getSchemaName(): string
    {
        return (string) $this->getAttribute('schema_name');
    }

    protected $dispatchesEvents = [
        'saving' => Events\SavingTenant::class,
        'saved' => Events\TenantSaved::class,
        'creating' => Events\CreatingTenant::class,
        'created' => Events\TenantCreated::class,
        'updating' => Events\TenantUpdated::class,
        'updated' => Events\TenantUpdated::class,
        'deleting' => Events\DeletingTenant::class,
        'deleted' => Events\TenantDeleted::class,
    ];
}
