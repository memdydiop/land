<?php

declare(strict_types=1);

use App\Actions\Tenancy\OnboardTenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::auth', ['title' => 'Créer mon organisation'])] class extends Component
{
    public string $organization_name = '';

    public string $organization_slug = '';

    public string $owner_name = '';

    public string $owner_email = '';

    public string $owner_password = '';

    public string $owner_password_confirmation = '';

    public string $timezone = 'Africa/Abidjan';

    public string $locale = 'fr';

    public string $currency = 'XOF';

    public function save(OnboardTenant $onboardTenant): void
    {
        $this->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_slug' => ['nullable', 'string', 'alpha_dash', 'max:50'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'string', Password::default(), 'confirmed'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $result = $onboardTenant->execute([
            'organization_name' => $this->organization_name,
            'organization_slug' => $this->organization_slug ?: null,
            'owner_name' => $this->owner_name,
            'owner_email' => $this->owner_email,
            'owner_password' => $this->owner_password,
            'owner_password_confirmation' => $this->owner_password_confirmation,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'currency' => $this->currency,
        ]);

        Auth::login($result->owner);

        $this->redirect(
            'https://'.$result->domain->domain.'/dashboard',
            navigate: false,
        );
    }
};
?>

<form wire:submit="save" class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Créer mon organisation') }}</flux:heading>
        <flux:text>{{ __('Créez votre compte administrateur et votre organisation LAND.') }}</flux:text>
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Votre compte') }}</flux:heading>

        <flux:input wire:model="owner_name" :label="__('Nom complet')" type="text" required autofocus autocomplete="name" />
        <flux:input wire:model="owner_email" :label="__('Adresse e-mail')" type="email" required autocomplete="email" />
        <flux:input wire:model="owner_password" :label="__('Mot de passe')" type="password" required autocomplete="new-password" viewable />
        <flux:input wire:model="owner_password_confirmation" :label="__('Confirmation du mot de passe')" type="password" required autocomplete="new-password" viewable />
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Votre organisation') }}</flux:heading>

        <flux:input wire:model="organization_name" :label="__('Nom de l’organisation')" type="text" required />
        <flux:input wire:model="organization_slug" :label="__('Identifiant de l’organisation')" type="text" autocomplete="off" />
        <flux:text size="sm">{{ __('Votre adresse LAND sera créée automatiquement sous la forme : votre-slug.land.ci') }}</flux:text>
    </div>

    <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
        <span wire:loading.remove>{{ __('Créer mon organisation') }}</span>
        <span wire:loading>{{ __('Création de votre organisation…') }}</span>
    </flux:button>

    <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>{{ __('Vous avez déjà un compte ?') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('Se connecter') }}</flux:link>
    </div>
</form>
