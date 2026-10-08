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
        'reference' => '5H-FIN-PARCEL',
        'parcel_number' => '5H-FIN-PARCEL',
        'status' => 'provisional',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('commercial_offers')->insert([
        'id' => $offerId,
        'reference' => '5H-FIN-OFFER',
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
        'reference' => '5H-FIN-RES',
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
        'reference' => '5H-FIN-SALE',
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
        'reference' => '5H-FIN-CONTRACT',
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
        'reference' => '5H-FIN-INV',
        'invoice_number' => '5H-FIN-INV-N',
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
