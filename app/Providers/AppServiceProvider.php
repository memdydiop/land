<?php

namespace App\Providers;

use App\Actions\Tenancy\BootstrapTenant;
use App\Actions\Tenancy\ResumeFailedTenant;
use App\Contracts\Tenancy\FailedTenantResumer;
use App\Contracts\Tenancy\TenantBootstrapper;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TenantBootstrapper::class,
            BootstrapTenant::class,
        );

        $this->app->bind(
            FailedTenantResumer::class,
            ResumeFailedTenant::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureEmailVerificationUrls();
    }

    /**
     * Configure verification URLs for central and tenant users.
     */
    protected function configureEmailVerificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (User $user): string {
            $tenant = tenant();
            $expiresAt = now()->addMinutes(
                (int) config('auth.verification.expire', 60),
            );

            $parameters = [
                'id' => (string) $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ];

            if ($tenant instanceof Tenant) {
                return URL::temporarySignedRoute(
                    'tenant.verification.verify',
                    $expiresAt,
                    [
                        'tenant' => $tenant->slug,
                        ...$parameters,
                    ],
                );
            }

            return URL::temporarySignedRoute(
                'verification.verify',
                $expiresAt,
                $parameters,
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
