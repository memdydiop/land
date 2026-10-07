<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('sale is created from a confirmed reservation and freezes converted offer items', function () {
    $tenant = $this->testTenant('terra_sale_basic');

    $tenant->run(function (): void {
        $partyId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationId = (string) Str::ulid();
        $saleId = (string) Str::ulid();
        $offerItemId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $partyId,
            'type' => 'person',
            'display_name' => 'Sale Buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'SALE-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Sale test offer',
            'price' => 20000000,
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
            'agreed_price' => 20000000,
            'deposit_amount' => 1000000,
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
            'agreed_price' => 20000000,
            'currency' => 'XOF',
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('sales')
                ->where('id', $saleId)
                ->value('reservation_id')
        )->toBe($reservationId);

        DB::table('reservations')
            ->where('id', $reservationId)
            ->update([
                'status' => 'converted',
                'updated_at' => now(),
            ]);

        expect(fn () => DB::table('commercial_offer_items')
            ->where('id', $offerItemId)
            ->update([
                'position' => 1,
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);
    });
});

test('a reservation can produce only one sale', function () {
    $tenant = $this->testTenant('terra_sale_unique_reservation');

    $tenant->run(function (): void {
        $partyId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $partyId,
            'type' => 'person',
            'display_name' => 'Unique Sale Buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'UNIQUE-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Unique sale offer',
            'price' => 10000000,
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
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'confirmed',
            'reserved_at' => now(),
            'agreed_price' => 10000000,
            'deposit_amount' => 500000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $baseSale = [
            'reservation_id' => $reservationId,
            'buyer_party_id' => $partyId,
            'type' => 'land',
            'status' => 'confirmed',
            'agreed_price' => 10000000,
            'currency' => 'XOF',
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('sales')->insert([
            'id' => (string) Str::ulid(),
            'reference' => 'SALE-'.Str::upper(Str::random(10)),
            ...$baseSale,
        ]);

        expect(fn () => DB::table('sales')->insert([
            'id' => (string) Str::ulid(),
            'reference' => 'SALE-'.Str::upper(Str::random(10)),
            ...$baseSale,
        ]))->toThrow(QueryException::class);
    });
});

test('one sale can have multiple contracts and each contract can have multiple invoices', function () {
    $tenant = $this->testTenant('terra_sale_contracts_invoices');

    $tenant->run(function (): void {
        $partyId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationId = (string) Str::ulid();
        $saleId = (string) Str::ulid();
        $contractOneId = (string) Str::ulid();
        $contractTwoId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $partyId,
            'type' => 'person',
            'display_name' => 'Contract Buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'CONTRACT-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Contract chain offer',
            'price' => 30000000,
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
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'confirmed',
            'reserved_at' => now(),
            'agreed_price' => 30000000,
            'deposit_amount' => 1500000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales')->insert([
            'id' => $saleId,
            'reservation_id' => $reservationId,
            'buyer_party_id' => $partyId,
            'reference' => 'SALE-'.Str::upper(Str::random(10)),
            'type' => 'real_estate',
            'status' => 'confirmed',
            'agreed_price' => 30000000,
            'currency' => 'XOF',
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$contractOneId, 'CONTRACT-ONE'],
            [$contractTwoId, 'CONTRACT-TWO'],
        ] as [$contractId, $reference]) {
            DB::table('contracts')->insert([
                'id' => $contractId,
                'party_id' => $partyId,
                'sale_id' => $saleId,
                'project_id' => null,
                'reference' => $reference,
                'title' => $reference,
                'type' => 'sale',
                'status' => 'active',
                'amount' => 30000000,
                'currency' => 'XOF',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            [$contractOneId, 'INV-ONE-1'],
            [$contractOneId, 'INV-ONE-2'],
            [$contractTwoId, 'INV-TWO-1'],
        ] as [$contractId, $invoiceNumber]) {
            DB::table('invoices')->insert([
                'id' => (string) Str::ulid(),
                'client_party_id' => $partyId,
                'project_id' => null,
                'contract_id' => $contractId,
                'reference' => $invoiceNumber,
                'invoice_number' => $invoiceNumber,
                'status' => 'issued',
                'issue_date' => now()->toDateString(),
                'due_date' => null,
                'subtotal' => 10000000,
                'tax' => 0,
                'total' => 10000000,
                'currency' => 'XOF',
                'work_situation_id' => null,
                'invoice_type' => 'sale',
                'numbering_year' => now()->year,
                'issued_at' => now(),
                'retention_amount' => 0,
                'net_amount' => 10000000,
                'immutable_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        expect(
            DB::table('contracts')
                ->where('sale_id', $saleId)
                ->count()
        )->toBe(2);

        expect(
            DB::table('invoices')
                ->whereIn('contract_id', [$contractOneId, $contractTwoId])
                ->count()
        )->toBe(3);
    });
});

test('converted reservation prevents a second reservation on the same target', function () {
    $tenant = $this->testTenant('terra_sale_re_reservation');

    $tenant->run(function (): void {
        $partyOneId = (string) Str::ulid();
        $partyTwoId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerOneId = (string) Str::ulid();
        $offerTwoId = (string) Str::ulid();
        $reservationOneId = (string) Str::ulid();
        $reservationTwoId = (string) Str::ulid();

        foreach ([
            [$partyOneId, 'Buyer One'],
            [$partyTwoId, 'Buyer Two'],
        ] as [$partyId, $displayName]) {
            DB::table('parties')->insert([
                'id' => $partyId,
                'type' => 'person',
                'display_name' => $displayName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'RESALE-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$offerOneId, 'Offer One'],
            [$offerTwoId, 'Offer Two'],
        ] as [$offerId, $title]) {
            DB::table('commercial_offers')->insert([
                'id' => $offerId,
                'reference' => 'OFFER-'.Str::upper(Str::random(10)),
                'title' => $title,
                'price' => 25000000,
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
        }

        DB::table('reservations')->insert([
            'id' => $reservationOneId,
            'commercial_offer_id' => $offerOneId,
            'party_id' => $partyOneId,
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'confirmed',
            'reserved_at' => now(),
            'agreed_price' => 25000000,
            'deposit_amount' => 1000000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $reservationOneId,
            'buyer_party_id' => $partyOneId,
            'reference' => 'SALE-'.Str::upper(Str::random(10)),
            'type' => 'land',
            'status' => 'completed',
            'agreed_price' => 25000000,
            'currency' => 'XOF',
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reservations')
            ->where('id', $reservationOneId)
            ->update([
                'status' => 'converted',
                'updated_at' => now(),
            ]);

        DB::table('reservations')->insert([
            'id' => $reservationTwoId,
            'commercial_offer_id' => $offerTwoId,
            'party_id' => $partyTwoId,
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'reserved_at' => now(),
            'agreed_price' => 25000000,
            'deposit_amount' => 1000000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwoId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);
    });
});

test('sale schema exposes explicit commercial fields', function () {
    $tenant = $this->testTenant('terra_sale_schema');

    $tenant->run(function (): void {
        expect(Schema::hasColumn('sales', 'reservation_id'))->toBeTrue()
            ->and(Schema::hasColumn('sales', 'buyer_party_id'))->toBeTrue()
            ->and(Schema::hasColumn('sales', 'agreed_price'))->toBeTrue()
            ->and(Schema::hasColumn('sales', 'currency'))->toBeTrue()
            ->and(Schema::hasColumn('sales', 'sold_at'))->toBeTrue()
            ->and(Schema::hasColumn('contracts', 'sale_id'))->toBeTrue();
    });
});
