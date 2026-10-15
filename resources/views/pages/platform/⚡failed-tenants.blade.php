<?php

declare(strict_types=1);

use App\Actions\Platform\ResumeFailedTenantFromPlatform;
use App\Enums\TenantStatus;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Reprise des tenants en échec'])] class extends Component
{
    #[Locked]
    public ?string $selectedTenantId = null;

    public string $ownerEmail = '';

    public string $ownerName = '';

    public string $ownerPassword = '';

    public string $ownerPasswordConfirmation = '';

    public ?string $successMessage = null;

    public ?string $failureMessage = null;

    public function mount(): void
    {
        $this->assertGhost();
    }

    #[Computed]
    public function failedTenants(): EloquentCollection
    {
        $this->assertGhost();

        return Tenant::query()
            ->where('status', TenantStatus::Failed->value)
            ->with('domains')
            ->orderBy('updated_at')
            ->get();
    }

    #[Computed]
    public function recentAuditLogs(): EloquentCollection
    {
        $this->assertGhost();

        return PlatformAuditLog::query()
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function selectedTenant(): ?Tenant
    {
        $this->assertGhost();

        if ($this->selectedTenantId === null) {
            return null;
        }

        return Tenant::query()
            ->whereKey($this->selectedTenantId)
            ->where('status', TenantStatus::Failed->value)
            ->first();
    }

    public function selectTenant(string $tenantId): void
    {
        $this->assertGhost();

        $tenant = Tenant::query()
            ->whereKey($tenantId)
            ->where('status', TenantStatus::Failed->value)
            ->firstOrFail();

        $this->selectedTenantId = (string) $tenant->getKey();
        $this->ownerEmail = '';
        $this->ownerName = '';
        $this->ownerPassword = '';
        $this->ownerPasswordConfirmation = '';
        $this->successMessage = null;
        $this->failureMessage = null;
        $this->resetValidation();
    }

    public function cancelSelection(): void
    {
        $this->assertGhost();

        $this->reset(
            'selectedTenantId',
            'ownerEmail',
            'ownerName',
            'ownerPassword',
            'ownerPasswordConfirmation',
        );

        $this->successMessage = null;
        $this->failureMessage = null;
        $this->resetValidation();
    }

    public function resume(ResumeFailedTenantFromPlatform $action): void
    {
        $actor = auth()->user();

        abort_unless(
            $actor instanceof User && $actor->hasRole('Ghost'),
            403,
        );

        $this->successMessage = null;
        $this->failureMessage = null;
        $this->resetValidation();

        if ($this->selectedTenantId === null) {
            $this->addError('selectedTenantId', 'Sélectionnez un tenant en échec.');

            return;
        }

        $data = $this->validate([
            'ownerEmail' => ['required', 'string', 'email', 'max:255'],
            'ownerName' => ['nullable', 'string', 'max:255'],
            'ownerPassword' => ['nullable', 'string'],
            'ownerPasswordConfirmation' => ['nullable', 'string'],
        ]);

        try {
            $result = $action->execute(
                $actor,
                $this->selectedTenantId,
                [
                    'owner_email' => $data['ownerEmail'],
                    'owner_name' => $data['ownerName'] ?: null,
                    'owner_password' => $data['ownerPassword'] ?: null,
                    'owner_password_confirmation' => $data['ownerPasswordConfirmation'] ?: null,
                ],
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            $this->failureMessage =
                'La reprise a échoué. Consultez le journal d’audit central pour le résultat enregistré.';

            $this->ownerPassword = '';
            $this->ownerPasswordConfirmation = '';

            return;
        }

        $this->successMessage = sprintf(
            'Le tenant « %s » a été réactivé.',
            $result->tenant->name,
        );

        $this->reset(
            'selectedTenantId',
            'ownerEmail',
            'ownerName',
            'ownerPassword',
            'ownerPasswordConfirmation',
        );

        unset($this->failedTenants);
        unset($this->selectedTenant);
    }

    private function assertGhost(): void
    {
        abort_unless(
            auth()->user()?->hasRole('Ghost') === true,
            403,
        );
    }
};
?>

<div class="space-y-6">
    <div class="space-y-2">
        <flux:heading size="xl">Reprise des tenants en échec</flux:heading>
        <flux:subheading>
            Examinez les organisations dont le provisionnement a échoué.
            Chaque tentative de reprise est enregistrée dans le journal central.
        </flux:subheading>
    </div>

    @if ($successMessage)
        <flux:callout variant="success" icon="check-circle" heading="{{ $successMessage }}" />
    @endif

    @if ($failureMessage)
        <flux:callout variant="danger" icon="x-circle" heading="{{ $failureMessage }}" />
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-3 font-medium">Organisation</th>
                    <th class="px-4 py-3 font-medium">Identifiant</th>
                    <th class="px-4 py-3 font-medium">Domaine principal</th>
                    <th class="px-4 py-3 font-medium">Dernière modification</th>
                    <th class="px-4 py-3 font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->failedTenants as $tenant)
                    <tr wire:key="failed-tenant-{{ $tenant->getKey() }}">
                        <td class="px-4 py-3 font-medium">{{ $tenant->name }}</td>
                        <td class="px-4 py-3">{{ $tenant->slug }}</td>
                        <td class="px-4 py-3">
                            {{ $tenant->domains->firstWhere('is_primary', true)?->domain ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $tenant->updated_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <flux:button
                                size="sm"
                                variant="primary"
                                type="button"
                                wire:click="selectTenant('{{ $tenant->getKey() }}')"
                            >
                                Préparer la reprise
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                            Aucun tenant en échec.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->selectedTenant)
        <section class="space-y-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="space-y-1">
                <flux:heading size="lg">Confirmer la reprise</flux:heading>
                <flux:text>
                    Organisation : <strong>{{ $this->selectedTenant->name }}</strong>
                </flux:text>
                <flux:text>
                    Indiquez l’adresse e-mail du propriétaire existant. Si aucun propriétaire
                    n’existe, fournissez aussi son nom et un mot de passe. Le mot de passe
                    n’est jamais écrit dans le journal d’audit.
                </flux:text>
            </div>

            <form wire:submit="resume" class="space-y-4">
                <flux:input
                    wire:model="ownerEmail"
                    label="Adresse e-mail du propriétaire"
                    type="email"
                    required
                    autocomplete="off"
                />
                @error('ownerEmail')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror
                @error('owner_email')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror

                <flux:input
                    wire:model="ownerName"
                    label="Nom du nouveau propriétaire (si nécessaire)"
                    type="text"
                    autocomplete="off"
                />
                @error('ownerName')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror
                @error('owner_name')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror

                <flux:input
                    wire:model="ownerPassword"
                    label="Mot de passe du nouveau propriétaire (si nécessaire)"
                    type="password"
                    autocomplete="new-password"
                    viewable
                />
                @error('ownerPassword')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror
                @error('owner_password')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror

                <flux:input
                    wire:model="ownerPasswordConfirmation"
                    label="Confirmer le mot de passe"
                    type="password"
                    autocomplete="new-password"
                    viewable
                />

                @error('owner_password_confirmation')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror
                @error('selectedTenantId')
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                @enderror

                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" variant="danger" wire:loading.attr="disabled">
                        Confirmer et reprendre
                    </flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelSelection">
                        Annuler
                    </flux:button>
                </div>
            </form>
        </section>
    @endif

    <section class="space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Journal des opérations</flux:heading>
            <flux:subheading>
                Les 20 dernières tentatives réalisées sur la plateforme.
                Les détails sensibles des exceptions ne sont pas affichés ici.
            </flux:subheading>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">Début</th>
                        <th class="px-4 py-3 font-medium">Opérateur</th>
                        <th class="px-4 py-3 font-medium">Organisation</th>
                        <th class="px-4 py-3 font-medium">Résultat</th>
                        <th class="px-4 py-3 font-medium">Code d’échec</th>
                        <th class="px-4 py-3 font-medium">Fin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->recentAuditLogs as $audit)
                        <tr wire:key="platform-audit-{{ $audit->getKey() }}">
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $audit->started_at?->format('d/m/Y H:i:s') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $audit->actor_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $audit->actor_email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $audit->tenant_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $audit->tenant_slug }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $audit->status }}</td>
                            <td class="px-4 py-3">{{ $audit->failure_code ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $audit->completed_at?->format('d/m/Y H:i:s') ?? 'En cours' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                Aucune opération enregistrée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
