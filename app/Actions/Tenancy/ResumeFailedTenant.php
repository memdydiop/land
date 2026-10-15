<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Contracts\Tenancy\TenantBootstrapper;
use App\Enums\TenantStatus;
use App\Jobs\Tenancy\MigrateTenantDatabase;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use LogicException;
use RuntimeException;
use Throwable;

final class ResumeFailedTenant
{
    public function __construct(
        private TenantBootstrapper $bootstrapTenant,
        private ActivateTenant $activateTenant,
    ) {}

    /**
     * Resume provisioning for an existing failed tenant.
     *
     * Existing owner credentials are never overwritten.
     *
     * @param  array<string, mixed>  $ownerInput
     */
    public function execute(
        Tenant $tenant,
        array $ownerInput,
    ): TenantOnboardingResult {
        $validated = Validator::make($ownerInput, [
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_password' => ['nullable', 'string'],
        ])->validate();

        /*
         * Claim the failed tenant before doing any recovery work.
         * The status transition prevents a concurrent recovery attempt.
         */
        $tenantId = (string) $tenant->getKey();

        $tenant = DB::transaction(function () use ($tenantId): Tenant {
            $lockedTenant = Tenant::query()
                ->whereKey($tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTenant->status !== TenantStatus::Failed->value) {
                throw new LogicException(
                    'Only failed tenants can be resumed.'
                );
            }

            $lockedTenant->forceFill([
                'status' => TenantStatus::Provisioning->value,
            ])->saveQuietly();

            return $lockedTenant->fresh();
        });

        $createdDomainId = null;
        $originalDomainState = null;

        try {
            /*
             * Reuse an existing schema. Create it only if it is absent.
             * Never drop a schema during recovery.
             */
            $schemaManager = $tenant->database()->manager();
            $schemaName = $tenant->database()->getName();

            if (! $schemaManager->databaseExists($schemaName)) {
                if (! $schemaManager->createDatabase($tenant)) {
                    throw new RuntimeException(
                        "Unable to create tenant schema [{$schemaName}]."
                    );
                }
            }

            /*
             * Laravel runs only pending migrations through tenants:migrate.
             * The application job also propagates a non-zero exit code.
             */
            (new MigrateTenantDatabase($tenant))->handle();

            $this->bootstrapTenant->execute($tenant);

            $domainName = $tenant->slug.'.'.config(
                'tenancy.land_domain',
                'land.ci',
            );

            $domain = Domain::query()
                ->where('domain', $domainName)
                ->first();

            if (
                $domain !== null
                && (string) $domain->tenant_id !== (string) $tenant->getKey()
            ) {
                throw new RuntimeException(
                    "The generated domain [{$domainName}] belongs to another tenant."
                );
            }

            $primaryDomain = $tenant->domains()
                ->where('is_primary', true)
                ->first();

            if (
                $primaryDomain !== null
                && $primaryDomain->domain !== $domainName
            ) {
                throw new RuntimeException(
                    'The tenant already has a different primary domain.'
                );
            }

            $email = strtolower(trim($validated['owner_email']));

            /*
             * Reuse the matching owner if present.
             * If an owner exists under a different email, stop instead of
             * silently creating a second owner.
             */
            $owner = $tenant->run(function () use (
                $email,
                $ownerInput,
            ): User {
                $owners = User::role('owner')->get();
                $existingOwner = $owners->firstWhere('email', $email);

                if ($owners->isNotEmpty()) {
                    if ($existingOwner === null) {
                        throw new LogicException(
                            'A tenant owner already exists with a different email. '
                            .'Resolve the owner identity before resuming.'
                        );
                    }

                    return $existingOwner;
                }

                $user = User::query()->where('email', $email)->first();

                if ($user !== null && ! $user->hasRole('owner')) {
                    throw new LogicException(
                        'A tenant user with the requested owner email already exists '
                        .'but is not an owner. Resolve the owner identity before resuming.'
                    );
                }

                if ($user === null) {
                    $ownerData = Validator::make($ownerInput, [
                        'owner_name' => [
                            'required',
                            'string',
                            'max:255',
                        ],
                        'owner_password' => [
                            'required',
                            'string',
                            Password::default(),
                            'confirmed',
                        ],
                    ])->validate();

                    $user = User::create([
                        'name' => $ownerData['owner_name'],
                        'email' => $email,
                        'password' => $ownerData['owner_password'],
                        'email_verified_at' => now(),
                    ]);
                }

                if (! $user->hasRole('owner')) {
                    $user->assignRole('owner');
                }

                return $user->fresh();
            });

            if ($domain === null) {
                $domain = Domain::create([
                    'tenant_id' => $tenant->getKey(),
                    'domain' => $domainName,
                    'is_primary' => true,
                    'verified_at' => now(),
                ]);

                $createdDomainId = $domain->getKey();
            } else {
                $changes = [];

                if (! $domain->is_primary) {
                    $changes['is_primary'] = true;
                }

                if ($domain->verified_at === null) {
                    $changes['verified_at'] = now();
                }

                if ($changes !== []) {
                    $originalDomainState = [
                        'id' => $domain->getKey(),
                        'is_primary' => $domain->is_primary,
                        'verified_at' => $domain->verified_at,
                    ];

                    $domain->forceFill($changes)->save();
                }
            }

            $this->activateTenant->execute($tenant);

            return new TenantOnboardingResult(
                $tenant->fresh(),
                $owner,
                $domain->fresh(),
            );
        } catch (Throwable $exception) {
            /*
             * Remove only a domain created during this recovery attempt.
             * Preserve any pre-existing domain and always retain the schema.
             */
            if ($createdDomainId !== null) {
                try {
                    Domain::query()
                        ->whereKey($createdDomainId)
                        ->where('tenant_id', $tenant->getKey())
                        ->delete();
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            if ($originalDomainState !== null) {
                try {
                    $domainToRestore = Domain::query()
                        ->whereKey($originalDomainState['id'])
                        ->where('tenant_id', $tenant->getKey())
                        ->first();

                    if ($domainToRestore !== null) {
                        $domainToRestore->forceFill([
                            'is_primary' => $originalDomainState['is_primary'],
                            'verified_at' => $originalDomainState['verified_at'],
                        ])->save();
                    }
                } catch (Throwable $restoreException) {
                    report($restoreException);
                }
            }

            try {
                $tenant->forceFill([
                    'status' => TenantStatus::Failed->value,
                ])->saveQuietly();
            } catch (Throwable $statusException) {
                report($statusException);
            }

            throw $exception;
        }
    }
}
