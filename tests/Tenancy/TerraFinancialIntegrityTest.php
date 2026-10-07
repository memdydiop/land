<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

$buildSaleContext = static function (): array {
    $partyId = (string) Str::ulid();
    $parcelId = (string) Str::ulid();
    $offerId = (string) Str::ulid();
    $offerItemId = (string) Str::ulid();
    $reservationId = (string) Str::ulid();
    $saleId = (string) Str::ulid();

    DB::table('parties')->insert([
        'id' => $partyId,
        'type' => 'person',
        'display_name' => 'Financial Buyer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('parcels')->insert([
        'id' => $parcelId,
        'ilot_id' => null,
        'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
        'parcel_number' => 'FIN-'.$parcelId,
        'status' => 'provisional',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('commercial_offers')->insert([
        'id' => $offerId,
        'reference' => 'OFFER-'.Str::upper(Str::random(10)),
        'title' => 'Financial offer',
        'price' => '30000000.0000',
        'currency' => 'XOF',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('commercial_offer_items')->insert([
        'id' => $offerItemId,
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
        'reference' => 'RES-'.Str::upper(Str::random(10)),
        'status' => 'confirmed',
        'reserved_at' => now(),
        'agreed_price' => '30000000.0000',
        'deposit_amount' => '1000000.0000',
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sales')->insert([
        'id' => $saleId,
        'reservation_id' => $reservationId,
        'buyer_party_id' => $partyId,
        'reference' => 'SALE-'.Str::upper(Str::random(10)),
        'type' => 'land',
        'status' => 'confirmed',
        'agreed_price' => '30000000.0000',
        'currency' => 'XOF',
        'sold_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('partyId', 'reservationId', 'saleId');
};

test('financial schema exposes reservation direction type and refund links', function () {
    $tenant = $this->testTenant('terra_financial_schema_v2');

    $tenant->run(function (): void {
        expect(Schema::hasColumn('payments', 'reservation_id'))->toBeTrue()
            ->and(Schema::hasColumn('payments', 'direction'))->toBeTrue()
            ->and(Schema::hasColumn('payments', 'type'))->toBeTrue()
            ->and(Schema::hasColumn('payments', 'refund_of'))->toBeTrue()
            ->and(Schema::hasTable('payment_allocations'))->toBeTrue();
    });
});

test('a reservation deposit can exist before any invoice', function () use ($buildSaleContext) {
    $tenant = $this->testTenant('terra_financial_reservation_payment_v2');

    $tenant->run(function () use ($buildSaleContext): void {
        $context = $buildSaleContext();

        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $context['reservationId'],
            'amount' => '1000000.0000',
            'currency' => 'XOF',
            'direction' => 'incoming',
            'type' => 'deposit',
            'method' => 'mobile_money',
            'reference' => 'DEP-'.Str::upper(Str::random(10)),
            'status' => 'confirmed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('payment_allocations')
                ->count()
        )->toBe(0);
    });
});

test('payment linked to reservation must use reservation currency', function () use ($buildSaleContext) {
    $tenant = $this->testTenant('terra_financial_currency_v2');

    $tenant->run(function () use ($buildSaleContext): void {
        $context = $buildSaleContext();

        expect(fn () => DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $context['reservationId'],
            'amount' => '1000.0000',
            'currency' => 'EUR',
            'direction' => 'incoming',
            'type' => 'deposit',
            'method' => 'bank_transfer',
            'status' => 'confirmed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('contract and invoice identities must match their terra chain', function () use ($buildSaleContext) {
    $tenant = $this->testTenant('terra_financial_contract_invoice_v2');

    $tenant->run(function () use ($buildSaleContext): void {
        $context = $buildSaleContext();
        $otherPartyId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $otherPartyId,
            'type' => 'person',
            'display_name' => 'Other Financial Party',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('contracts')->insert([
            'id' => (string) Str::ulid(),
            'party_id' => $otherPartyId,
            'sale_id' => $context['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-BAD-'.Str::upper(Str::random(8)),
            'title' => 'Bad contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        $contractId = (string) Str::ulid();

        DB::table('contracts')->insert([
            'id' => $contractId,
            'party_id' => $context['partyId'],
            'sale_id' => $context['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-'.Str::upper(Str::random(8)),
            'title' => 'Valid contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceBase = [
            'project_id' => null,
            'contract_id' => $contractId,
            'reference' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'subtotal' => '1000000.0000',
            'tax' => '0.0000',
            'total' => '1000000.0000',
            'work_situation_id' => null,
            'invoice_type' => 'advance',
            'numbering_year' => now()->year,
            'issued_at' => now(),
            'retention_amount' => '0.0000',
            'net_amount' => '1000000.0000',
            'immutable_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        expect(fn () => DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => $otherPartyId,
            'currency' => 'XOF',
            ...$invoiceBase,
        ]))->toThrow(QueryException::class);

        expect(fn () => DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => $context['partyId'],
            'currency' => 'EUR',
            ...$invoiceBase,
        ]))->toThrow(QueryException::class);
    });
});

test('payment allocation cannot exceed payment or invoice', function () use ($buildSaleContext) {
    $tenant = $this->testTenant('terra_financial_allocations_v2');

    $tenant->run(function () use ($buildSaleContext): void {
        $context = $buildSaleContext();
        $contractId = (string) Str::ulid();
        $invoiceOneId = (string) Str::ulid();
        $invoiceTwoId = (string) Str::ulid();
        $paymentId = (string) Str::ulid();

        DB::table('contracts')->insert([
            'id' => $contractId,
            'party_id' => $context['partyId'],
            'sale_id' => $context['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-'.Str::upper(Str::random(8)),
            'title' => 'Allocation contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$invoiceOneId, '5000000.0000'],
            [$invoiceTwoId, '5000000.0000'],
        ] as [$invoiceId, $amount]) {
            DB::table('invoices')->insert([
                'id' => $invoiceId,
                'client_party_id' => $context['partyId'],
                'project_id' => null,
                'contract_id' => $contractId,
                'reference' => 'INV-'.Str::upper(Str::random(10)),
                'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
                'status' => 'issued',
                'issue_date' => now()->toDateString(),
                'due_date' => null,
                'subtotal' => $amount,
                'tax' => '0.0000',
                'total' => $amount,
                'currency' => 'XOF',
                'work_situation_id' => null,
                'invoice_type' => 'advance',
                'numbering_year' => now()->year,
                'issued_at' => now(),
                'retention_amount' => '0.0000',
                'net_amount' => $amount,
                'immutable_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('payments')->insert([
            'id' => $paymentId,
            'reservation_id' => $context['reservationId'],
            'amount' => '6000000.0000',
            'currency' => 'XOF',
            'direction' => 'incoming',
            'type' => 'invoice_payment',
            'method' => 'bank_transfer',
            'status' => 'confirmed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceOneId,
            'amount' => '5000000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceTwoId,
            'amount' => '2000000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('refunds cannot exceed the original confirmed incoming payment', function () use ($buildSaleContext) {
    $tenant = $this->testTenant('terra_financial_refunds_v2');

    $tenant->run(function () use ($buildSaleContext): void {
        $context = $buildSaleContext();
        $originalPaymentId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $originalPaymentId,
            'reservation_id' => $context['reservationId'],
            'amount' => '1000000.0000',
            'currency' => 'XOF',
            'direction' => 'incoming',
            'type' => 'deposit',
            'method' => 'mobile_money',
            'status' => 'confirmed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $context['reservationId'],
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $originalPaymentId,
            'status' => 'confirmed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $context['reservationId'],
            'amount' => '700000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $originalPaymentId,
            'status' => 'confirmed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});
