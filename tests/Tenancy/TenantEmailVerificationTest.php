<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use App\Actions\Tenancy\OnboardTenant;
use App\Actions\Tenancy\TenantOnboardingResult;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TenancyTestCase;

class TenantEmailVerificationTest extends TenancyTestCase
{
    public function test_registration_sends_a_signed_verification_link_and_verifies_the_owner(): void
    {
        Notification::fake();

        $suffix = Str::lower(Str::random(12));
        $slug = 'verify-'.$suffix;
        $email = 'owner-'.$suffix.'@example.test';

        $applicationUrl = parse_url((string) config('app.url')) ?: [];
        $scheme = is_string($applicationUrl['scheme'] ?? null)
            ? $applicationUrl['scheme']
            : 'http';
        $port = isset($applicationUrl['port'])
            ? ':'.$applicationUrl['port']
            : '';
        $domain = $slug.'.'.config('tenancy.land_domain', 'land.ci');

        Livewire::test('pages::auth.register')
            ->set('organization_name', 'Verification Test Organization')
            ->set('organization_slug', $slug)
            ->set('owner_name', 'Verification Test Owner')
            ->set('owner_email', $email)
            ->set('owner_password', 'Secret-password-123')
            ->set('owner_password_confirmation', 'Secret-password-123')
            ->set('timezone', 'Africa/Abidjan')
            ->set('locale', 'fr')
            ->set('currency', 'XOF')
            ->call('save')
            ->assertRedirect(
                "{$scheme}://{$domain}{$port}/login?registered=1",
            );

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $owner = $tenant->run(
            fn (): User => User::query()
                ->where('email', $email)
                ->firstOrFail(),
        );

        $this->assertNull($owner->email_verified_at);
        Notification::assertSentTo($owner, VerifyEmail::class);

        /** @var VerifyEmail $notification */
        $notification = Notification::sent($owner, VerifyEmail::class)->first();

        $verificationUrl = $tenant->run(
            fn (): string => (string) $notification->toMail($owner)->actionUrl,
        );

        $this->assertSame(
            config('tenancy.central_domains')[0],
            parse_url($verificationUrl, PHP_URL_HOST),
        );
        $this->assertStringContainsString(
            '/tenant/email/verify/'.$slug.'/'.$owner->getKey().'/',
            (string) parse_url($verificationUrl, PHP_URL_PATH),
        );

        $response = $this->get($verificationUrl);
        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $this->assertIsString($redirectUrl);
        $this->assertSame($domain, parse_url($redirectUrl, PHP_URL_HOST));
        $this->assertSame('/login', parse_url($redirectUrl, PHP_URL_PATH));

        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertSame('1', $query['verified'] ?? null);

        $this->assertTrue(
            $tenant->run(
                fn (): bool => User::query()
                    ->whereKey((string) $owner->getKey())
                    ->firstOrFail()
                    ->hasVerifiedEmail(),
            ),
        );
    }

    public function test_signed_link_with_an_email_hash_for_another_address_is_rejected(): void
    {
        $suffix = Str::lower(Str::random(12));
        $slug = 'wrong-hash-'.$suffix;
        $email = 'owner-'.$suffix.'@example.test';

        $result = $this->onboardTenant($slug, $email);

        $invalidUrl = URL::temporarySignedRoute(
            'tenant.verification.verify',
            now()->addMinutes(5),
            [
                'tenant' => $result->tenant->slug,
                'id' => (string) $result->owner->getKey(),
                'hash' => sha1('another-person@example.test'),
            ],
        );

        $this->get($invalidUrl)->assertForbidden();

        $this->assertFalse(
            $result->tenant->run(
                fn (): bool => User::query()
                    ->whereKey((string) $result->owner->getKey())
                    ->firstOrFail()
                    ->hasVerifiedEmail(),
            ),
        );
    }

    private function onboardTenant(
        string $slug,
        string $email,
    ): TenantOnboardingResult {
        return app(OnboardTenant::class)->execute([
            'organization_name' => 'Email Verification Test',
            'organization_slug' => $slug,
            'owner_name' => 'Email Verification Owner',
            'owner_email' => $email,
            'owner_password' => 'Secret-password-123',
            'owner_password_confirmation' => 'Secret-password-123',
            'timezone' => 'Africa/Abidjan',
            'locale' => 'fr',
            'currency' => 'XOF',
        ]);
    }
}
