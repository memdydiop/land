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
