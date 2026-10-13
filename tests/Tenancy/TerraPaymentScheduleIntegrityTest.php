<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

$buildSale = static function (): array {
    $partyId = (string) Str::ulid();
    $parcelId = (string) Str::ulid();
    $offerId = (string) Str::ulid();
    $reservationId = (string) Str::ulid();
    $saleId = (string) Str::ulid();

    DB::table('parties')->insert([
        'id' => $partyId,
        'type' => 'person',
        'display_name' => 'Schedule Buyer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('parcels')->insert([
        'id' => $parcelId,
        'ilot_id' => null,
        'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
        'parcel_number' => 'SCHED-'.$parcelId,
        'status' => 'provisional',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('commercial_offers')->insert([
        'id' => $offerId,
        'reference' => 'OFFER-'.Str::upper(Str::random(10)),
        'title' => 'Payment schedule offer',
        'price' => '30000000.0000',
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

    return compact('partyId', 'saleId');
};

$insertScheduleItem = static function (
    string $saleId,
    int $position,
    string $label,
    string $triggerType,
    ?string $triggerReference,
    ?string $dueOn,
    string $amount,
    ?string $percentage = null,
): string {
    $id = (string) Str::ulid();

    DB::table('payment_schedule_items')->insert([
        'id' => $id,
        'sale_id' => $saleId,
        'position' => $position,
        'label' => $label,
        'trigger_type' => $triggerType,
        'trigger_reference' => $triggerReference,
        'due_on' => $dueOn,
        'amount' => $amount,
        'percentage' => $percentage,
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
};

test('payment schedule schema is available', function () {
    $tenant = $this->testTenant('terra_payment_schedule_schema_v1');

    $tenant->run(function (): void {
        expect(Schema::hasTable('payment_schedule_items'))->toBeTrue()
            ->and(Schema::hasColumn('payment_schedule_items', 'sale_id'))->toBeTrue()
            ->and(Schema::hasColumn('payment_schedule_items', 'due_on'))->toBeTrue()
            ->and(Schema::hasColumn('payment_schedule_items', 'amount'))->toBeTrue()
            ->and(Schema::hasColumn('payment_schedule_items', 'percentage'))->toBeTrue()
            ->and(Schema::hasColumn('invoices', 'schedule_item_id'))->toBeTrue();
    });
});

test('sale can have date and milestone schedule items', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_items_v1');

    $tenant->run(function () use ($buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $first = $insertScheduleItem(
            $sale['saleId'],
            1,
            'Signature',
            'date',
            null,
            '2026-11-15',
            '10000000.0000',
            '33.3333',
        );

        $second = $insertScheduleItem(
            $sale['saleId'],
            2,
            'Achèvement des fondations',
            'milestone',
            'FOUNDATIONS_COMPLETE',
            null,
            '20000000.0000',
            '66.6667',
        );

        expect(DB::table('payment_schedule_items')->whereIn('id', [$first, $second])->count())
            ->toBe(2);
    });
});

test('schedule amounts cannot exceed sale agreed price', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_amount_v1');

    $tenant->run(function () use ($buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $insertScheduleItem(
            $sale['saleId'],
            1,
            'First call',
            'date',
            null,
            '2026-11-01',
            '20000000.0000',
        );

        expect(fn () => $insertScheduleItem(
            $sale['saleId'],
            2,
            'Second call',
            'date',
            null,
            '2026-12-01',
            '11000000.0000',
        ))->toThrow(QueryException::class);
    });
});

test('concurrent schedule items serialize on sale and cannot exceed agreed price', function () use ($buildSale) {
    $tenant = $this->testTenant('terra_payment_schedule_sale_concurrency');

    $tenant->run(function () use ($tenant, $buildSale): void {
        $sale = $buildSale();

        $makeScheduleItem = static function (
            int $position,
            string $label
        ) use ($sale): array {
            return [
                'id' => (string) Str::ulid(),
                'sale_id' => $sale['saleId'],
                'position' => $position,
                'label' => $label,
                'trigger_type' => 'date',
                'trigger_reference' => null,
                'due_on' => $position === 1 ? '2026-11-01' : '2026-12-01',
                'amount' => '20000000.0000',
                'percentage' => null,
                'currency' => 'XOF',
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

            // Session A locks the sale before Session B attempts its schedule item.
            $first->beginTransaction();

            $first->selectOne(
                'SELECT id FROM sales WHERE id = ? FOR UPDATE',
                [$sale['saleId']],
            );

            $second->statement("SET lock_timeout TO '250ms'");
            $second->beginTransaction();

            $lockException = null;

            try {
                $second->table('payment_schedule_items')->insert(
                    $makeScheduleItem(2, 'Second call')
                );
            } catch (QueryException $exception) {
                $lockException = $exception;
            }

            expect($lockException)->toBeInstanceOf(QueryException::class)
                ->and($lockException->errorInfo[0] ?? null)->toBe('55P03');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            // Session A commits the first 20,000,000 XOF schedule item.
            $first->table('payment_schedule_items')->insert(
                $makeScheduleItem(1, 'First call')
            );

            $first->commit();

            // Session B retries; 40,000,000 XOF exceeds the sale price of 30,000,000.
            $second->statement("SET lock_timeout TO '0'");
            $second->beginTransaction();

            $limitException = null;

            try {
                $second->table('payment_schedule_items')->insert(
                    $makeScheduleItem(2, 'Second call')
                );
            } catch (QueryException $exception) {
                $limitException = $exception;
            }

            expect($limitException)->toBeInstanceOf(QueryException::class)
                ->and($limitException->errorInfo[0] ?? null)->toBe('23514');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            expect(
                $first->table('payment_schedule_items')
                    ->where('sale_id', $sale['saleId'])
                    ->count()
            )->toBe(1)
                ->and(
                    $first->table('payment_schedule_items')
                        ->where('sale_id', $sale['saleId'])
                        ->sum('amount')
                )->toBe('20000000.0000');
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

test('schedule percentages cannot exceed one hundred percent', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_percentage_v1');

    $tenant->run(function () use ($buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $insertScheduleItem(
            $sale['saleId'],
            1,
            'First call',
            'date',
            null,
            '2026-11-01',
            '10000000.0000',
            '60.0000',
        );

        expect(fn () => $insertScheduleItem(
            $sale['saleId'],
            2,
            'Second call',
            'date',
            null,
            '2026-12-01',
            '10000000.0000',
            '41.0000',
        ))->toThrow(QueryException::class);
    });
});

test('schedule trigger requires the corresponding date or milestone reference', function () use ($buildSale) {
    $tenant = $this->testTenant('terra_payment_schedule_trigger_v1');

    $tenant->run(function () use ($buildSale): void {
        $sale = $buildSale();

        expect(fn () => DB::table('payment_schedule_items')->insert([
            'id' => (string) Str::ulid(),
            'sale_id' => $sale['saleId'],
            'position' => 1,
            'label' => 'Invalid date trigger',
            'trigger_type' => 'date',
            'trigger_reference' => null,
            'due_on' => null,
            'amount' => '10000000.0000',
            'percentage' => null,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        expect(fn () => DB::table('payment_schedule_items')->insert([
            'id' => (string) Str::ulid(),
            'sale_id' => $sale['saleId'],
            'position' => 2,
            'label' => 'Invalid milestone trigger',
            'trigger_type' => 'milestone',
            'trigger_reference' => null,
            'due_on' => null,
            'amount' => '10000000.0000',
            'percentage' => null,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('invoice linked to schedule item must belong to the same sale contract', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_invoice_chain_v1');

    $tenant->run(function () use ($buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $scheduleItemId = $insertScheduleItem(
            $sale['saleId'],
            1,
            'Advance',
            'date',
            null,
            '2026-11-01',
            '10000000.0000',
        );

        $contractId = (string) Str::ulid();

        DB::table('contracts')->insert([
            'id' => $contractId,
            'party_id' => $sale['partyId'],
            'sale_id' => $sale['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-'.Str::upper(Str::random(8)),
            'title' => 'Schedule contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => $sale['partyId'],
            'project_id' => null,
            'contract_id' => $contractId,
            'schedule_item_id' => $scheduleItemId,
            'reference' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'subtotal' => '10000000.0000',
            'tax' => '0.0000',
            'total' => '10000000.0000',
            'currency' => 'XOF',
            'work_situation_id' => null,
            'invoice_type' => 'advance',
            'numbering_year' => now()->year,
            'issued_at' => now(),
            'retention_amount' => '0.0000',
            'net_amount' => '10000000.0000',
            'immutable_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('invoices')
                ->where('schedule_item_id', $scheduleItemId)
                ->count()
        )->toBe(1);
    });
});

test('invoices cannot exceed a schedule item amount', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_invoice_cap_v1');

    $tenant->run(function () use ($buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $scheduleItemId = $insertScheduleItem(
            $sale['saleId'],
            1,
            'Advance',
            'date',
            null,
            '2026-11-01',
            '5000000.0000',
        );

        $contractId = (string) Str::ulid();

        DB::table('contracts')->insert([
            'id' => $contractId,
            'party_id' => $sale['partyId'],
            'sale_id' => $sale['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-'.Str::upper(Str::random(8)),
            'title' => 'Invoice cap contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceData = [
            'project_id' => null,
            'contract_id' => $contractId,
            'schedule_item_id' => $scheduleItemId,
            'client_party_id' => $sale['partyId'],
            'reference' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'tax' => '0.0000',
            'currency' => 'XOF',
            'work_situation_id' => null,
            'invoice_type' => 'advance',
            'numbering_year' => now()->year,
            'issued_at' => now(),
            'retention_amount' => '0.0000',
            'net_amount' => '3000000.0000',
            'immutable_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'subtotal' => '3000000.0000',
            'total' => '3000000.0000',
            ...$invoiceData,
        ]);

        expect(fn () => DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'subtotal' => '2500000.0000',
            'total' => '2500000.0000',
            ...$invoiceData,
            'reference' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
        ]))->toThrow(QueryException::class);
    });
});

test('concurrent invoices serialize on payment schedule item and cannot exceed its amount', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_invoice_concurrency');

    $tenant->run(function () use ($tenant, $buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $scheduleItemId = $insertScheduleItem(
            $sale['saleId'],
            1,
            'Advance',
            'date',
            null,
            '2026-11-01',
            '5000000.0000',
        );

        $contractId = (string) Str::ulid();

        DB::table('contracts')->insert([
            'id' => $contractId,
            'party_id' => $sale['partyId'],
            'sale_id' => $sale['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-'.Str::upper(Str::random(8)),
            'title' => 'Concurrent invoice cap contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $makeInvoice = static function () use ($sale, $contractId, $scheduleItemId): array {
            return [
                'id' => (string) Str::ulid(),
                'client_party_id' => $sale['partyId'],
                'project_id' => null,
                'contract_id' => $contractId,
                'schedule_item_id' => $scheduleItemId,
                'reference' => 'INV-'.Str::upper(Str::random(10)),
                'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
                'status' => 'issued',
                'issue_date' => now()->toDateString(),
                'due_date' => null,
                'subtotal' => '3000000.0000',
                'tax' => '0.0000',
                'total' => '3000000.0000',
                'currency' => 'XOF',
                'work_situation_id' => null,
                'invoice_type' => 'advance',
                'numbering_year' => now()->year,
                'issued_at' => now(),
                'retention_amount' => '0.0000',
                'net_amount' => '3000000.0000',
                'immutable_at' => null,
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

            // Session A reserves the schedule item while Session B attempts an invoice.
            $first->beginTransaction();

            $first->selectOne(
                'SELECT id FROM payment_schedule_items WHERE id = ? FOR UPDATE',
                [$scheduleItemId],
            );

            $second->statement("SET lock_timeout TO '250ms'");
            $second->beginTransaction();

            $lockException = null;

            try {
                $second->table('invoices')->insert($makeInvoice());
            } catch (QueryException $exception) {
                $lockException = $exception;
            }

            expect($lockException)->toBeInstanceOf(QueryException::class)
                ->and($lockException->errorInfo[0] ?? null)->toBe('55P03');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            // Session A commits the first 3,000,000 XOF invoice.
            $first->table('invoices')->insert($makeInvoice());
            $first->commit();

            // Session B retries: 6,000,000 XOF would exceed the 5,000,000 cap.
            $second->statement("SET lock_timeout TO '0'");
            $second->beginTransaction();

            $limitException = null;

            try {
                $second->table('invoices')->insert($makeInvoice());
            } catch (QueryException $exception) {
                $limitException = $exception;
            }

            expect($limitException)->toBeInstanceOf(QueryException::class)
                ->and($limitException->errorInfo[0] ?? null)->toBe('23514');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            expect(
                $first->table('invoices')
                    ->where('schedule_item_id', $scheduleItemId)
                    ->count()
            )->toBe(1)
                ->and(
                    $first->table('invoices')
                        ->where('schedule_item_id', $scheduleItemId)
                        ->sum('total')
                )->toBe('3000000.0000');
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

test('schedule item is frozen after invoice creation', function () use ($buildSale, $insertScheduleItem) {
    $tenant = $this->testTenant('terra_payment_schedule_freeze_v1');

    $tenant->run(function () use ($buildSale, $insertScheduleItem): void {
        $sale = $buildSale();

        $scheduleItemId = $insertScheduleItem(
            $sale['saleId'],
            1,
            'Advance',
            'date',
            null,
            '2026-11-01',
            '5000000.0000',
        );

        $contractId = (string) Str::ulid();

        DB::table('contracts')->insert([
            'id' => $contractId,
            'party_id' => $sale['partyId'],
            'sale_id' => $sale['saleId'],
            'project_id' => null,
            'reference' => 'CONTRACT-'.Str::upper(Str::random(8)),
            'title' => 'Freeze contract',
            'type' => 'sale',
            'status' => 'active',
            'amount' => '30000000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'client_party_id' => $sale['partyId'],
            'project_id' => null,
            'contract_id' => $contractId,
            'schedule_item_id' => $scheduleItemId,
            'reference' => 'INV-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'subtotal' => '3000000.0000',
            'tax' => '0.0000',
            'total' => '3000000.0000',
            'currency' => 'XOF',
            'work_situation_id' => null,
            'invoice_type' => 'advance',
            'numbering_year' => now()->year,
            'issued_at' => now(),
            'retention_amount' => '0.0000',
            'net_amount' => '3000000.0000',
            'immutable_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payment_schedule_items')
            ->where('id', $scheduleItemId)
            ->update([
                'amount' => '4000000.0000',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);
    });
});
