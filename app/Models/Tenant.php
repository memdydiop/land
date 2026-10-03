<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Stancl\Tenancy\Contracts;
use Stancl\Tenancy\Database\Concerns;
use Stancl\Tenancy\Database\TenantCollection;
use Stancl\Tenancy\Events;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model implements Contracts\TenantWithDatabase
{
    use Concerns\CentralConnection,
        Concerns\GeneratesIds,
        Concerns\HasInternalKeys,
        Concerns\TenantRun,
        Concerns\InvalidatesResolverCache,
        Concerns\HasDatabase,
        Concerns\HasDomains;

    protected static $modelsShouldPreventAccessingMissingAttributes = false;

    protected $table = 'tenants';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey()
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    public function newCollection(array $models = []): TenantCollection
    {
        return new TenantCollection($models);
    }

    protected $dispatchesEvents = [
        'saving' => Events\SavingTenant::class,
        'saved' => Events\TenantSaved::class,
        'creating' => Events\CreatingTenant::class,
        'created' => Events\TenantCreated::class,
        'updating' => Events\UpdatingTenant::class,
        'updated' => Events\TenantUpdated::class,
        'deleting' => Events\DeletingTenant::class,
        'deleted' => Events\TenantDeleted::class,
    ];
}
