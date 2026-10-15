<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Contracts\Tenancy\TenantBootstrapper;
use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

final class OnboardTenant
{
    public function __construct(
        private CreateTenant $createTenant,
        private TenantBootstrapper $bootstrapTenant,
    ) {}

    /** @param array<string,mixed> $input */
    public function execute(array $input): TenantOnboardingResult
    {
        $validated = Validator::make($input, [
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_slug' => ['nullable', 'string', 'alpha_dash', 'max:50'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'string', Password::default(), 'confirmed'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
        ])->validate();

        $tenant = $this->createTenant->execute([
            'name' => $validated['organization_name'],
            'slug' => $validated['organization_slug'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'locale' => $validated['locale'] ?? 'en',
            'currency' => $validated['currency'] ?? 'XOF',
        ]);

        try {
            $this->bootstrapTenant->execute($tenant);

            $owner = $tenant->run(function () use ($validated): User {
                $user = User::create([
                    'name' => $validated['owner_name'],
                    'email' => $validated['owner_email'],
                    'password' => $validated['owner_password'],
                    'email_verified_at' => now(),
                ]);

                $user->assignRole('owner');

                return $user->fresh();
            });

            $domain = Domain::create([
                'tenant_id' => $tenant->getKey(),
                'domain' => $tenant->slug.'.'.config('tenancy.land_domain', 'land.ci'),
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            $tenant->update([
                'status' => TenantStatus::Active->value,
            ]);

            return new TenantOnboardingResult(
                $tenant->fresh(),
                $owner,
                $domain->fresh(),
            );
        } catch (Throwable $exception) {
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
