<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreateTenant
{
    /** @param array{name:string,slug?:string|null,timezone?:string,locale?:string,currency?:string} $input */
    public function execute(array $input): Tenant
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:50', 'unique:tenants,slug'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
        ])->validate();

        $slug = Str::slug($validated['slug'] ?? $validated['name']);

        Validator::make(['slug' => $slug], [
            'slug' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:tenants,slug'],
        ])->validate();

        return Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => TenantStatus::Provisioning->value,
            'schema_name' => 'tenant_'.$slug,
            'database_identifier' => null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'locale' => $validated['locale'] ?? 'en',
            'currency' => strtoupper($validated['currency'] ?? 'XOF'),
            'data' => [],
        ])->fresh();
    }
}
