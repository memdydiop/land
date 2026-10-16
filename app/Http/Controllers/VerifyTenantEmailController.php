<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;

final class VerifyTenantEmailController
{
    public function __invoke(
        string $tenant,
        string $id,
        string $hash,
    ): RedirectResponse {
        $tenantModel = Tenant::query()
            ->where('slug', $tenant)
            ->firstOrFail();

        abort_unless(
            $tenantModel->status === TenantStatus::Active->value,
            404,
        );

        // Resolve the primary domain while still in the central context.
        $domain = Domain::query()
            ->where('tenant_id', $tenantModel->getKey())
            ->where('is_primary', true)
            ->value('domain');

        abort_unless(is_string($domain) && $domain !== '', 404);

        // The signed URL proves access to the mailbox; authentication
        // on the tenant domain is not required to complete verification.
        $alreadyVerified = (bool) $tenantModel->run(
            function () use ($id, $hash): bool {
                $user = User::query()->findOrFail($id);

                abort_unless(
                    hash_equals(
                        sha1($user->getEmailForVerification()),
                        $hash,
                    ),
                    403,
                );

                if ($user->hasVerifiedEmail()) {
                    return true;
                }

                abort_unless($user->markEmailAsVerified(), 500);

                event(new Verified($user));

                return false;
            },
        );

        $applicationUrl = parse_url((string) config('app.url')) ?: [];
        $scheme = is_string($applicationUrl['scheme'] ?? null)
            ? $applicationUrl['scheme']
            : 'https';
        $port = isset($applicationUrl['port'])
            ? ':'.$applicationUrl['port']
            : '';
        $status = $alreadyVerified ? 'already' : '1';

        return redirect()->away(
            "{$scheme}://{$domain}{$port}/login?verified={$status}",
        );
    }
}
