<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

function terra5hFinancialFixture(): array
{
    $partyId = (string) Str::ulid();
    $offerId = (string) Str::ulid();
    $parcelId = (string) Str::ulid();
    $reservationId = (string) Str::ulid();
    $saleId = (string) Str::ulid();
    $contractId = (string) Str::ulid();
    $invoiceId = (string) Str::ulid();
    $paymentId = (string) Str::ulid();

    DB::table('parties')->insert([
        'id' => $partyId,
        'type' => 'person',
        'display_name' => '5H Financial Buyer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('parcels')->insert([
        'id' => $parcelId,
        'ilot_id' => null,
        'reference' => '5H-FIN-PARCEL-'.Str::upper(Str::random(10)),
        'parcel_number' => '5H-FIN-PARCEL-'.Str::upper(Str::random(10)),
        'status' => 'provisional',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('commercial_offers')->insert([
        'id' => $offerId,
        'reference' => '5H-FIN-OFFER-'.Str::upper(Str::random(10)),
        'title' => '5H Financial Offer',
        'price' => '10000000.0000',
        'currency' => 'XOF',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('commercial_offer_items')->insert([
        'id' => (string) Str::ulid(),
        'commercial_offer_id' => $offerId,
        'parcel_id' => $parcelId,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('reservations')->insert([
        'id' => $reservationId,
        'commercial_offer_id' => $offerId,
        'party_id' => $partyId,
        'reference' => '5H-FIN-RES-'.Str::upper(Str::random(10)),
        'status' => 'confirmed',
        'reserved_at' => now(),
        'agreed_price' => '10000000.0000',
        'deposit_amount' => '500000.0000',
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sales')->insert([
        'id' => $saleId,
        'reservation_id' => $reservationId,
        'buyer_party_id' => $partyId,
        'reference' => '5H-FIN-SALE-'.Str::upper(Str::random(10)),
        'type' => 'land',
        'status' => 'confirmed',
        'agreed_price' => '10000000.0000',
        'currency' => 'XOF',
        'sold_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contracts')->insert([
        'id' => $contractId,
        'party_id' => $partyId,
        'sale_id' => $saleId,
        'project_id' => null,
        'reference' => '5H-FIN-CONTRACT-'.Str::upper(Str::random(10)),
        'title' => '5H Financial Contract',
        'type' => 'sale',
        'status' => 'active',
        'amount' => '10000000.0000',
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('invoices')->insert([
        'id' => $invoiceId,
        'client_party_id' => $partyId,
        'project_id' => null,
        'contract_id' => $contractId,
        'reference' => '5H-FIN-INV-'.Str::upper(Str::random(10)),
        'invoice_number' => '5H-FIN-INV-N-'.Str::upper(Str::random(10)),
        'status' => 'issued',
        'issue_date' => now()->toDateString(),
        'due_date' => null,
        'subtotal' => '1000000.0000',
        'tax' => '0.0000',
        'total' => '1000000.0000',
        'currency' => 'XOF',
        'work_situation_id' => null,
        'invoice_type' => 'advance',
        'numbering_year' => now()->year,
        'issued_at' => now(),
        'retention_amount' => '0.0000',
        'net_amount' => '1000000.0000',
        'immutable_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('payments')->insert([
        'id' => $paymentId,
        'reservation_id' => $reservationId,
        'amount' => '1000000.0000',
        'currency' => 'XOF',
        'direction' => 'incoming',
        'type' => 'invoice_payment',
        'method' => 'bank_transfer',
        'status' => 'pending',
        'paid_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('paymentId', 'invoiceId');
}

test('pending payment cannot be allocated', function () {
    $tenant = $this->testTenant('terra_5h_payment_pending_allocation');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        expect(fn () => DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '100000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('allocated payment cannot be cancelled or failed', function () {
    $tenant = $this->testTenant('terra_5h_payment_status_frozen');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')->where('id', $fixture['paymentId'])->update(['status' => 'confirmed']);
        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '100000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')->where('id', $fixture['paymentId'])->update(['status' => 'cancelled']))
            ->toThrow(QueryException::class);

        expect(fn () => DB::table('payments')->where('id', $fixture['paymentId'])->update(['status' => 'failed']))
            ->toThrow(QueryException::class);
    });
});

test('payment amount cannot be reduced below existing allocations', function () {
    $tenant = $this->testTenant('terra_5h_payment_amount_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '600000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['amount' => '500000.0000'])
        )->toThrow(QueryException::class);

        expect(
            DB::table('payments')
                ->where('id', $fixture['paymentId'])
                ->value('amount')
        )->toBe('1000000.0000');
    });
});

test('issued credit notes cannot consume an already allocated invoice balance', function () {
    $tenant = $this->testTenant('terra_5h_credit_note_allocation_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '600000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => DB::table('invoices')
                ->where('id', $fixture['invoiceId'])
                ->value('client_party_id'),
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'reason' => 'Financial integrity test',
            'subtotal' => '500000.0000',
            'tax' => '0.0000',
            'total' => '500000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('payment allocation is capped by invoice total after issued credit notes', function () {
    $tenant = $this->testTenant('terra_5h_credit_note_collectible_balance');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('credit_notes')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => DB::table('invoices')
                ->where('id', $fixture['invoiceId'])
                ->value('client_party_id'),
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'reason' => 'Financial integrity test',
            'subtotal' => '300000.0000',
            'tax' => '0.0000',
            'total' => '300000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '800000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '700000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('payment_allocations')
                ->where('invoice_id', $fixture['invoiceId'])
                ->sum('amount')
        )->toBe('700000.0000');
    });
});

test('payment allocation state validation locks the payment row', function () {
    $tenant = $this->testTenant('terra_5h_payment_state_lock');

    $tenant->run(function (): void {
        $definition = DB::selectOne(<<<'SQL'
            SELECT pg_get_functiondef(p.oid) AS definition
            FROM pg_proc p
            JOIN pg_namespace n
              ON n.oid = p.pronamespace
            WHERE n.nspname = current_schema()
              AND p.proname = 'terra_validate_payment_allocation_state'
            SQL);

        expect($definition)->not->toBeNull()
            ->and(strtolower($definition->definition))->toContain('for update');
    });
});
