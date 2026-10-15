<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Actions\Tenancy\TenantOnboardingResult;
use App\Contracts\Tenancy\FailedTenantResumer;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

final class ResumeFailedTenantFromPlatform
{
    public function __construct(
        private FailedTenantResumer $resumer,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $ownerInput
     */
    public function execute(
        User $actor,
        string $tenantId,
        array $ownerInput,
    ): TenantOnboardingResult {
        if (! $actor->hasRole('Ghost')) {
            throw new AuthorizationException(
                'Only the Ghost platform administrator may resume tenants.'
            );
        }

        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        // Persist the audit record before performing recovery.
        $audit = PlatformAuditLog::query()->create([
            'event' => 'tenant.resume',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'tenant_id' => $tenant->getKey(),
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            $result = $this->resumer->execute($tenant, $ownerInput);
        } catch (Throwable $exception) {
            $failureCode = match (true) {
                $exception instanceof ValidationException => 'validation_failed',
                $exception instanceof LogicException => 'recovery_precondition_failed',
                $exception instanceof RuntimeException => 'provisioning_failed',
                default => 'unexpected_error',
            };

            try {
                $audit->forceFill([
                    'status' => 'failed',
                    'failure_code' => $failureCode,
                    'completed_at' => now(),
                ])->save();
            } catch (Throwable $auditException) {
                report($auditException);
            }

            throw $exception;
        }

        try {
            $audit->forceFill([
                'status' => 'succeeded',
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $auditException) {
            // Do not report a failed recovery when the recovery itself succeeded.
            report($auditException);
        }

        return $result;
    }
}
