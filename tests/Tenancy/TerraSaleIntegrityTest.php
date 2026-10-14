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
            'status' => 'draft',
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

        // A draft sale cannot convert its reservation.
        expect(fn () => DB::table('reservations')
            ->where('id', $reservationId)
            ->update([
                'status' => 'converted',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);

        // draft -> confirmed is allowed.
        DB::table('sales')
            ->where('id', $saleId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ]);

        // confirmed -> draft is forbidden.
        expect(fn () => DB::table('sales')
            ->where('id', $saleId)
            ->update([
                'status' => 'draft',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);

        // confirmed -> completed is allowed.
        DB::table('sales')
            ->where('id', $saleId)
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        // completed is terminal.
        expect(fn () => DB::table('sales')
            ->where('id', $saleId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);

        DB::table('reservations')
            ->where('id', $reservationId)
            ->update([
                'status' => 'converted',
                'updated_at' => now(),
            ]);

        // A converted reservation must retain its linked sale.
        expect(fn () => DB::table('sales')
            ->where('id', $saleId)
            ->delete()
        )->toThrow(QueryException::class);

        // An unrelated confirmed reservation is used to isolate reassignment.
        $otherParcelId = (string) Str::ulid();
        $otherOfferId = (string) Str::ulid();
        $otherReservationId = (string) Str::ulid();

        DB::table('parcels')->insert([
            'id' => $otherParcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'SALE-ALTERNATE-'.$otherParcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $otherOfferId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Alternate sale test offer',
            'price' => 20000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $otherOfferId,
            'parcel_id' => $otherParcelId,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reservations')->insert([
            'id' => $otherReservationId,
            'commercial_offer_id' => $otherOfferId,
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

        expect(fn () => DB::table('sales')
            ->where('id', $saleId)
            ->update([
                'reservation_id' => $otherReservationId,
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);

        // A converted reservation must remain the durable lock behind its sale.
        expect(fn () => DB::table('reservations')
            ->where('id', $reservationId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);

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

test('concurrent sale creation serializes on reservation and cannot create duplicates', function () {
    $tenant = $this->testTenant('terra_sale_concurrency_guard');

    $tenant->run(function () use ($tenant): void {
        $partyId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $partyId,
            'type' => 'person',
            'display_name' => 'Concurrent Sale Buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'CONCURRENT-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Concurrent sale offer',
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
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'confirmed',
            'reserved_at' => now(),
            'agreed_price' => '10000000.0000',
            'deposit_amount' => '500000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $makeSale = static function () use ($reservationId, $partyId): array {
            return [
                'id' => (string) Str::ulid(),
                'reservation_id' => $reservationId,
                'buyer_party_id' => $partyId,
                'reference' => 'SALE-'.Str::upper(Str::random(10)),
                'type' => 'land',
                'status' => 'confirmed',
                'agreed_price' => '10000000.0000',
                'currency' => 'XOF',
                'sold_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        };

        $first = DB::connection();
        $second = DB::connection('pgsql');

        $tenantSchema = $tenant->schema_name;
        $originalSearchPath = $second->selectOne(
            "SELECT current_setting('search_path') AS path"
        )->path;
        $originalLockTimeout = $second->selectOne(
            "SELECT current_setting('lock_timeout') AS timeout"
        )->timeout;

        try {
            $second->selectOne(
                "SELECT set_config('search_path', ?, false) AS path",
                [$tenantSchema.',public'],
            );

            expect($first->selectOne(
                'SELECT current_schema() AS schema'
            )->schema)->toBe($tenantSchema)
                ->and($second->selectOne(
                    'SELECT current_schema() AS schema'
                )->schema)->toBe($tenantSchema);

            // Session A locks the reservation before Session B attempts conversion.
            $first->beginTransaction();

            $first->selectOne(
                'SELECT id FROM reservations WHERE id = ? FOR UPDATE',
                [$reservationId],
            );

            $second->statement("SET lock_timeout TO '250ms'");
            $second->beginTransaction();

            $lockException = null;

            try {
                $second->table('sales')->insert($makeSale());
            } catch (QueryException $exception) {
                $lockException = $exception;
            }

            expect($lockException)->toBeInstanceOf(QueryException::class)
                ->and($lockException->errorInfo[0] ?? null)->toBe('55P03');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            // Session A converts the reservation and commits the first sale.
            $first->table('sales')->insert($makeSale());
            $first->commit();

            // Session B retries. The same reservation must not yield a second sale.
            $second->statement("SET lock_timeout TO '0'");
            $second->beginTransaction();

            $duplicateException = null;

            try {
                $second->table('sales')->insert($makeSale());
            } catch (QueryException $exception) {
                $duplicateException = $exception;
            }

            expect($duplicateException)->toBeInstanceOf(QueryException::class)
                ->and($duplicateException->errorInfo[0] ?? null)->toBe('23505');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            expect(
                $first->table('sales')
                    ->where('reservation_id', $reservationId)
                    ->count()
            )->toBe(1);
        } finally {
            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }

            $second->selectOne(
                "SELECT set_config('search_path', ?, false) AS path",
                [$originalSearchPath],
            );

            $second->selectOne(
                "SELECT set_config('lock_timeout', ?, false) AS timeout",
                [$originalLockTimeout],
            );
        }
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
