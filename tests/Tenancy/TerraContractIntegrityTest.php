<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

$createContract = static function (): array {
    $partyId = (string) Str::ulid();
    $contractId = (string) Str::ulid();

    DB::table('parties')->insert([
        'id' => $partyId,
        'type' => 'person',
        'display_name' => 'Contract Test Party',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contracts')->insert([
        'id' => $contractId,
        'party_id' => $partyId,
        'sale_id' => null,
        'project_id' => null,
        'reference' => 'CONTRACT-'.Str::upper(Str::random(10)),
        'title' => 'Contract integrity test',
        'type' => 'sale',
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'amount' => '10000000.0000',
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('partyId', 'contractId');
};

test('contract financial references use restrictive deletes', function () use ($createContract) {
    $tenant = $this->testTenant('terra_contract_fk_v1');

    $tenant->run(function () use ($createContract): void {
        expect(Schema::hasTable('contract_amendments'))->toBeTrue()
            ->and(Schema::hasTable('invoices'))->toBeTrue();

        $context = $createContract();

        DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => $context['partyId'],
            'project_id' => null,
            'contract_id' => $context['contractId'],
            'reference' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_type' => 'sale',
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'subtotal' => '1000000.0000',
            'tax' => '0.0000',
            'retention_amount' => '0.0000',
            'net_amount' => '1000000.0000',
            'total' => '1000000.0000',
            'currency' => 'XOF',
            'numbering_year' => now()->year,
            'issued_at' => now(),
            'immutable_at' => null,
            'work_situation_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('contracts')
            ->where('id', $context['contractId'])
            ->delete()
        )->toThrow(QueryException::class);
    });
});

test('an approved amendment requires approval metadata', function () use ($createContract) {
    $tenant = $this->testTenant('terra_contract_approved_meta_v1');

    $tenant->run(function () use ($createContract): void {
        $context = $createContract();

        expect(fn () => DB::table('contract_amendments')->insert([
            'id' => (string) Str::ulid(),
            'contract_id' => $context['contractId'],
            'reference' => 'AMD-'.Str::upper(Str::random(10)),
            'type' => 'amount',
            'status' => 'approved',
            'description' => 'Invalid approved amendment',
            'amount_delta' => '500000.0000',
            'new_amount' => '10500000.0000',
            'new_end_date' => null,
            'effective_date' => now()->toDateString(),
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('amendment type requires its corresponding target value', function () use ($createContract) {
    $tenant = $this->testTenant('terra_contract_type_guard_v1');

    $tenant->run(function () use ($createContract): void {
        $context = $createContract();

        expect(fn () => DB::table('contract_amendments')->insert([
            'id' => (string) Str::ulid(),
            'contract_id' => $context['contractId'],
            'reference' => 'AMD-'.Str::upper(Str::random(10)),
            'type' => 'amount',
            'status' => 'draft',
            'amount_delta' => '500000.0000',
            'new_amount' => null,
            'new_end_date' => null,
            'effective_date' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        expect(fn () => DB::table('contract_amendments')->insert([
            'id' => (string) Str::ulid(),
            'contract_id' => $context['contractId'],
            'reference' => 'AMD-'.Str::upper(Str::random(10)),
            'type' => 'duration',
            'status' => 'draft',
            'amount_delta' => '0.0000',
            'new_amount' => null,
            'new_end_date' => null,
            'effective_date' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('approved amendments are immutable', function () use ($createContract) {
    $tenant = $this->testTenant('terra_contract_amendment_immutable_v1');

    $tenant->run(function () use ($createContract): void {
        $context = $createContract();
        $userId = (string) Str::ulid();
        $amendmentId = (string) Str::ulid();

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Contract Approver',
            'email' => 'approver+'.Str::lower(Str::random(10)).'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contract_amendments')->insert([
            'id' => $amendmentId,
            'contract_id' => $context['contractId'],
            'reference' => 'AMD-'.Str::upper(Str::random(10)),
            'type' => 'amount',
            'status' => 'approved',
            'description' => 'Approved amount amendment',
            'amount_delta' => '500000.0000',
            'new_amount' => '10500000.0000',
            'new_end_date' => null,
            'effective_date' => now()->toDateString(),
            'approved_by' => $userId,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('contract_amendments')
            ->where('id', $amendmentId)
            ->update([
                'description' => 'Attempted mutation',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('contract_amendments')
            ->where('id', $amendmentId)
            ->delete()
        )->toThrow(QueryException::class);
    });
});

test('amendments cannot be created on terminal contracts', function () use ($createContract) {
    $tenant = $this->testTenant('terra_contract_terminal_v1');

    $tenant->run(function () use ($createContract): void {
        $context = $createContract();

        DB::table('contracts')
            ->where('id', $context['contractId'])
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        expect(fn () => DB::table('contract_amendments')->insert([
            'id' => (string) Str::ulid(),
            'contract_id' => $context['contractId'],
            'reference' => 'AMD-'.Str::upper(Str::random(10)),
            'type' => 'scope',
            'status' => 'draft',
            'description' => 'Invalid terminal amendment',
            'amount_delta' => '0.0000',
            'new_amount' => null,
            'new_end_date' => null,
            'effective_date' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});
