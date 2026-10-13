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

test('issued credit notes must belong to the invoice client', function () {
    $tenant = $this->testTenant('terra_credit_note_client_mismatch_on_issue');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        $invoiceClientId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $otherPartyId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $otherPartyId,
            'type' => 'person',
            'display_name' => 'Different Credit Note Client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $invoiceClientId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Client mismatch regression test',
            'subtotal' => '100000.0000',
            'tax' => '0.0000',
            'total' => '100000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Valid matching detail line',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '100000.0000',
            'tax_rate' => '0.000000',
            'total' => '100000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update([
                'client_party_id' => $otherPartyId,
                'status' => 'issued',
            ])
        )->toThrow(QueryException::class);
    });
});

test('draft credit note client cannot be reassigned to another party', function () {
    $tenant = $this->testTenant('terra_credit_note_client_reassignment');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        $invoiceClientId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $otherPartyId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $otherPartyId,
            'type' => 'person',
            'display_name' => 'Different Draft Credit Note Client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $invoiceClientId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Client reassignment regression test',
            'subtotal' => '100000.0000',
            'tax' => '0.0000',
            'total' => '100000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['client_party_id' => $otherPartyId])
        )->toThrow(QueryException::class);
    });
});

test('issued credit note total must match its detail line totals', function () {
    $tenant = $this->testTenant('terra_credit_note_detail_total_mismatch');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Credit note detail total regression test',
            'subtotal' => '200000.0000',
            'tax' => '0.0000',
            'total' => '200000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Detail deliberately below the note total',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '100000.0000',
            'tax_rate' => '0.000000',
            'total' => '100000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
    });
});

test('issued credit note total must equal subtotal plus tax', function () {
    $tenant = $this->testTenant('terra_credit_note_subtotal_tax_mismatch');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Credit note arithmetic regression test',
            'subtotal' => '150000.0000',
            'tax' => '0.0000',
            'total' => '200000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Line matching total but not subtotal',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '200000.0000',
            'tax_rate' => '0.000000',
            'total' => '200000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
    });
});

test('issued credit notes require at least one detail line', function () {
    $tenant = $this->testTenant('terra_credit_note_missing_detail_line');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Missing detail line regression test',
            'subtotal' => '100000.0000',
            'tax' => '0.0000',
            'total' => '100000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
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

        $creditNoteId = (string) Str::ulid();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Financial integrity test',
            'subtotal' => '500000.0000',
            'tax' => '0.0000',
            'total' => '500000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Matching detail line',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '500000.0000',
            'tax_rate' => '0.000000',
            'total' => '500000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
    });
});

test('payment allocation is capped by invoice total after issued credit notes', function () {
    $tenant = $this->testTenant('terra_5h_credit_note_collectible_balance');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        $creditNoteId = (string) Str::ulid();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Financial integrity test',
            'subtotal' => '300000.0000',
            'tax' => '0.0000',
            'total' => '300000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Matching detail line',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '300000.0000',
            'tax_rate' => '0.000000',
            'total' => '300000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued']);

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

test('payment allocation retries after credit note issuance and rejects the reduced collectible balance', function () {
    $tenant = $this->testTenant('terra_5h_credit_note_concurrency');

    $tenant->run(function () use ($tenant): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $creditNoteId = (string) Str::ulid();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Financial concurrency test',
            'subtotal' => '500000.0000',
            'tax' => '0.0000',
            'total' => '500000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Matching detail line',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '500000.0000',
            'tax_rate' => '0.000000',
            'total' => '500000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

            $firstSchema = $first->selectOne(
                'SELECT current_schema() AS schema'
            )->schema;

            $secondSchema = $second->selectOne(
                'SELECT current_schema() AS schema'
            )->schema;

            expect($firstSchema)->toBe($tenantSchema)
                ->and($secondSchema)->toBe($tenantSchema);

            // Session B must fail quickly rather than hang while session A holds the invoice.
            $second->statement("SET lock_timeout TO '250ms'");

            $first->beginTransaction();

            $first->selectOne(
                'SELECT id FROM invoices WHERE id = ? FOR UPDATE',
                [$fixture['invoiceId']],
            );

            $second->beginTransaction();

            $lockException = null;

            try {
                $second->table('payment_allocations')->insert([
                    'id' => (string) Str::ulid(),
                    'payment_id' => $fixture['paymentId'],
                    'invoice_id' => $fixture['invoiceId'],
                    'amount' => '600000.0000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $lockException = $exception;
            }

            expect($lockException)->toBeInstanceOf(QueryException::class);
            expect($lockException->errorInfo[0] ?? null)->toBe('55P03');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            // Session A owns the invoice lock and emits a valid 500,000 XOF credit note.
            $first->table('credit_notes')
                ->where('id', $creditNoteId)
                ->update(['status' => 'issued']);

            $first->commit();

            // Retry after the credit note is committed: 600,000 exceeds the 500,000 balance.
            $second->statement("SET lock_timeout TO '0'");
            $second->beginTransaction();

            $balanceException = null;

            try {
                $second->table('payment_allocations')->insert([
                    'id' => (string) Str::ulid(),
                    'payment_id' => $fixture['paymentId'],
                    'invoice_id' => $fixture['invoiceId'],
                    'amount' => '600000.0000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $balanceException = $exception;
            }

            expect($balanceException)->toBeInstanceOf(QueryException::class);
            expect($balanceException->errorInfo[0] ?? null)->toBe('23514');
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


test('invoice total cannot be reduced below allocations and issued credit notes', function () {
    $tenant = $this->testTenant('terra_invoice_total_credit_note_line_guard');

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

        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');
        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Invoice total integrity test',
            'subtotal' => '200000.0000',
            'tax' => '0.0000',
            'total' => '200000.0000',
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => (string) Str::ulid(),
            'credit_note_id' => $creditNoteId,
            'description' => 'Matching detail line',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '200000.0000',
            'tax_rate' => '0.000000',
            'total' => '200000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued']);

        // 600,000 allocated + 200,000 of issued credit notes.
        // Reducing the invoice to 700,000 breaks the invariant.
        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['total' => '700000.0000'])
        )->toThrow(QueryException::class);

        expect(
            DB::table('invoices')
                ->where('id', $fixture['invoiceId'])
                ->value('total')
        )->toBe('1000000.0000');
    });
});

test('new refunds must start in pending status', function () {
    $tenant = $this->testTenant('terra_refund_initial_status');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        foreach (['confirmed', 'failed', 'cancelled', 'reversed'] as $status) {
            expect(fn () => DB::table('payments')->insert([
                'id' => (string) Str::ulid(),
                'reservation_id' => $reservationId,
                'amount' => '100000.0000',
                'currency' => 'XOF',
                'direction' => 'outgoing',
                'type' => 'refund',
                'method' => 'bank_transfer',
                'refund_of' => $fixture['paymentId'],
                'status' => $status,
                'paid_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]))->toThrow(QueryException::class);
        }
    });
});

test('concurrent refund requests serialize on the original payment and cannot exceed its amount', function () {
    $tenant = $this->testTenant('terra_refund_concurrency_cap');

    $tenant->run(function () use ($tenant): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

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
            )->schema)->toBe($tenantSchema);

            expect($second->selectOne(
                'SELECT current_schema() AS schema'
            )->schema)->toBe($tenantSchema);

            // Session A holds the original payment lock.
            $first->beginTransaction();

            $first->selectOne(
                'SELECT id FROM payments WHERE id = ? FOR UPDATE',
                [$fixture['paymentId']],
            );

            // Session B tries to create a competing refund and must wait.
            $second->statement("SET lock_timeout TO '250ms'");
            $second->beginTransaction();

            $lockException = null;

            try {
                $second->table('payments')->insert([
                    'id' => (string) Str::ulid(),
                    'reservation_id' => $reservationId,
                    'amount' => '600000.0000',
                    'currency' => 'XOF',
                    'direction' => 'outgoing',
                    'type' => 'refund',
                    'method' => 'bank_transfer',
                    'refund_of' => $fixture['paymentId'],
                    'status' => 'pending',
                    'paid_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $lockException = $exception;
            }

            expect($lockException)->toBeInstanceOf(QueryException::class)
                ->and($lockException->errorInfo[0] ?? null)->toBe('55P03');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            // Session A records and commits the first 600,000 XOF refund.
            $first->table('payments')->insert([
                'id' => (string) Str::ulid(),
                'reservation_id' => $reservationId,
                'amount' => '600000.0000',
                'currency' => 'XOF',
                'direction' => 'outgoing',
                'type' => 'refund',
                'method' => 'bank_transfer',
                'refund_of' => $fixture['paymentId'],
                'status' => 'pending',
                'paid_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $first->commit();

            // Session B retries after the commit; 1,200,000 exceeds the original payment.
            $second->statement("SET lock_timeout TO '0'");
            $second->beginTransaction();

            $limitException = null;

            try {
                $second->table('payments')->insert([
                    'id' => (string) Str::ulid(),
                    'reservation_id' => $reservationId,
                    'amount' => '600000.0000',
                    'currency' => 'XOF',
                    'direction' => 'outgoing',
                    'type' => 'refund',
                    'method' => 'bank_transfer',
                    'refund_of' => $fixture['paymentId'],
                    'status' => 'pending',
                    'paid_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $limitException = $exception;
            }

            expect($limitException)->toBeInstanceOf(QueryException::class)
                ->and($limitException->errorInfo[0] ?? null)->toBe('23514');

            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            expect(
                $first->table('payments')
                    ->where('refund_of', $fixture['paymentId'])
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->sum('amount')
            )->toBe('600000.0000');
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

test('original payment cannot be reduced or invalidated while active refunds exist', function () {
    $tenant = $this->testTenant('terra_original_payment_refund_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $reservationId,
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $fixture['paymentId'],
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['amount' => '300000.0000'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'cancelled'])
        )->toThrow(QueryException::class);

        expect(
            DB::table('payments')
                ->where('id', $fixture['paymentId'])
                ->value('amount')
        )->toBe('1000000.0000');

        expect(
            DB::table('payments')
                ->where('id', $fixture['paymentId'])
                ->value('status')
        )->toBe('confirmed');
    });
});


test('invoice paid status requires settlement and cannot regress', function () {
    $tenant = $this->testTenant('terra_invoice_paid_lifecycle');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        // An unpaid invoice cannot be marked paid.
        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'paid'])
        )->toThrow(QueryException::class);

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '1000000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Full settlement permits paid.
        DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'paid']);

        // Paid is terminal; corrections use credit notes/refunds.
        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
    });
});

test('cancelled invoice cannot be reopened', function () {
    $tenant = $this->testTenant('terra_invoice_cancelled_terminal');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'cancelled']);

        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
    });
});

test('immutable invoice content cannot be edited or deleted', function () {
    $tenant = $this->testTenant('terra_invoice_immutable_content');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        // Setting immutable_at and changing fiscal content in one UPDATE
        // must not bypass the immutability guard.
        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update([
                'immutable_at' => now(),
                'total' => '900000.0000',
            ])
        )->toThrow(QueryException::class);

        expect(
            DB::table('invoices')
                ->where('id', $fixture['invoiceId'])
                ->value('total')
        )->toBe('1000000.0000');

        DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['immutable_at' => now()]);

        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['total' => '900000.0000'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->delete()
        )->toThrow(QueryException::class);
    });
});


test('issued credit notes become immutable with frozen detail lines', function () {
    $tenant = $this->testTenant('terra_credit_note_lifecycle_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $creditNoteId = (string) Str::ulid();
        $itemId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-N-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Credit note lifecycle test',
            'subtotal' => '200000.0000',
            'tax' => '0.0000',
            'total' => '200000.0000',
            'currency' => 'XOF',
            'immutable_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_note_items')->insert([
            'id' => $itemId,
            'credit_note_id' => $creditNoteId,
            'description' => 'Initial credit note line',
            'quantity' => '1.000000',
            'unit' => 'unit',
            'unit_price' => '200000.0000',
            'tax_rate' => '0.000000',
            'total' => '200000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update([
                'status' => 'issued',
                'updated_at' => now(),
            ]);

        expect(
            DB::table('credit_notes')
                ->where('id', $creditNoteId)
                ->value('immutable_at')
        )->not->toBeNull();

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'cancelled'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['total' => '150000.0000'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('credit_note_items')
            ->where('id', $itemId)
            ->update(['total' => '150000.0000'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('credit_note_items')
            ->where('id', $itemId)
            ->delete()
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->delete()
        )->toThrow(QueryException::class);
    });
});

test('cancelled credit notes cannot be reopened', function () {
    $tenant = $this->testTenant('terra_cancelled_credit_note_terminal');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();
        $clientPartyId = DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->value('client_party_id');

        $creditNoteId = (string) Str::ulid();

        DB::table('credit_notes')->insert([
            'id' => $creditNoteId,
            'client_party_id' => $clientPartyId,
            'invoice_id' => $fixture['invoiceId'],
            'reference' => 'CN-'.Str::upper(Str::random(10)),
            'credit_note_number' => 'CN-N-'.Str::upper(Str::random(10)),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'issued_at' => null,
            'reason' => 'Cancelled credit note test',
            'subtotal' => '100000.0000',
            'tax' => '0.0000',
            'total' => '100000.0000',
            'currency' => 'XOF',
            'immutable_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // L'annulation ne doit pas permettre de modifier le contenu
        // fiscal dans la même requête.
        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update([
                'status' => 'cancelled',
                'total' => '90000.0000',
            ])
        )->toThrow(QueryException::class);

        // L'annulation seule reste autorisée depuis l'état draft.
        DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'cancelled']);

        expect(fn () => DB::table('credit_notes')
            ->where('id', $creditNoteId)
            ->update(['status' => 'issued'])
        )->toThrow(QueryException::class);
    });
});


test('payment allocation backing a paid invoice cannot be deleted', function () {
    $tenant = $this->testTenant('terra_paid_invoice_allocation_delete_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '1000000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'paid']);

        expect(fn () => DB::table('payment_allocations')
            ->where('payment_id', $fixture['paymentId'])
            ->where('invoice_id', $fixture['invoiceId'])
            ->update(['amount' => '900000.0000'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('payment_allocations')
            ->where('payment_id', $fixture['paymentId'])
            ->where('invoice_id', $fixture['invoiceId'])
            ->delete()
        )->toThrow(QueryException::class);

        expect(
            DB::table('payment_allocations')
                ->where('payment_id', $fixture['paymentId'])
                ->where('invoice_id', $fixture['invoiceId'])
                ->exists()
        )->toBeTrue();

        expect(
            DB::table('invoices')
                ->where('id', $fixture['invoiceId'])
                ->value('status')
        )->toBe('paid');
    });
});


test('allocation backing a partially paid invoice cannot be reduced or deleted', function () {
    $tenant = $this->testTenant('terra_partial_invoice_allocation_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        DB::table('payment_allocations')->insert([
            'id' => (string) Str::ulid(),
            'payment_id' => $fixture['paymentId'],
            'invoice_id' => $fixture['invoiceId'],
            'amount' => '500000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')
            ->where('id', $fixture['invoiceId'])
            ->update(['status' => 'partially_paid']);

        expect(fn () => DB::table('payment_allocations')
            ->where('payment_id', $fixture['paymentId'])
            ->where('invoice_id', $fixture['invoiceId'])
            ->update(['amount' => '400000.0000'])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('payment_allocations')
            ->where('payment_id', $fixture['paymentId'])
            ->where('invoice_id', $fixture['invoiceId'])
            ->delete()
        )->toThrow(QueryException::class);

        expect(
            DB::table('payment_allocations')
                ->where('payment_id', $fixture['paymentId'])
                ->where('invoice_id', $fixture['invoiceId'])
                ->value('amount')
        )->toBe('500000.0000');

        expect(
            DB::table('invoices')
                ->where('id', $fixture['invoiceId'])
                ->value('status')
        )->toBe('partially_paid');
    });
});


test('refund payments require an original payment reference', function () {
    $tenant = $this->testTenant('terra_refund_requires_original_payment');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        expect(fn () => DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'reservation_id' => $reservationId,
            'amount' => '100000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => null,
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});


test('active refunds cannot be reclassified without their original payment', function () {
    $tenant = $this->testTenant('terra_active_refund_reclassification_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        $refundId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $refundId,
            'reservation_id' => $reservationId,
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $fixture['paymentId'],
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')
            ->where('id', $refundId)
            ->update([
                'type' => 'other',
                'refund_of' => null,
            ])
        )->toThrow(QueryException::class);
    });
});


test('active refunds cannot be deleted directly', function () {
    $tenant = $this->testTenant('terra_active_refund_delete_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        $refundId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $refundId,
            'reservation_id' => $reservationId,
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $fixture['paymentId'],
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')
            ->where('id', $refundId)
            ->delete()
        )->toThrow(QueryException::class);

        expect(
            DB::table('payments')
                ->where('id', $refundId)
                ->exists()
        )->toBeTrue();
    });
});


test('pending refunds can be cancelled without losing their original reference', function () {
    $tenant = $this->testTenant('terra_pending_refund_cancellation');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        $refundId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $refundId,
            'reservation_id' => $reservationId,
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $fixture['paymentId'],
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')
            ->where('id', $refundId)
            ->update(['status' => 'cancelled']);

        $refund = DB::table('payments')
            ->where('id', $refundId)
            ->first();

        expect($refund->status)->toBe('cancelled')
            ->and($refund->refund_of)->toBe($fixture['paymentId'])
            ->and($refund->type)->toBe('refund');
    });
});

test('ordinary payments cannot be converted into refunds', function () {
    $tenant = $this->testTenant('terra_payment_refund_reclassification');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        $ordinaryPaymentId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $ordinaryPaymentId,
            'reservation_id' => $reservationId,
            'amount' => '100000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'other',
            'method' => 'bank_transfer',
            'refund_of' => null,
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payments')
            ->where('id', $ordinaryPaymentId)
            ->update([
                'type' => 'refund',
                'refund_of' => $fixture['paymentId'],
            ])
        )->toThrow(QueryException::class);

        $payment = DB::table('payments')
            ->where('id', $ordinaryPaymentId)
            ->first();

        expect($payment->type)->toBe('other')
            ->and($payment->refund_of)->toBeNull();
    });
});


test('cancelled refund records cannot be deleted', function () {
    $tenant = $this->testTenant('terra_cancelled_refund_delete_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        $refundId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $refundId,
            'reservation_id' => $reservationId,
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $fixture['paymentId'],
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')
            ->where('id', $refundId)
            ->update(['status' => 'cancelled']);

        expect(fn () => DB::table('payments')
            ->where('id', $refundId)
            ->delete()
        )->toThrow(QueryException::class);

        expect(
            DB::table('payments')
                ->where('id', $refundId)
                ->exists()
        )->toBeTrue();
    });
});


test('confirmed refund amounts are immutable', function () {
    $tenant = $this->testTenant('terra_confirmed_refund_amount_guard');

    $tenant->run(function (): void {
        $fixture = terra5hFinancialFixture();

        DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->update(['status' => 'confirmed']);

        $reservationId = DB::table('payments')
            ->where('id', $fixture['paymentId'])
            ->value('reservation_id');

        $refundId = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $refundId,
            'reservation_id' => $reservationId,
            'amount' => '400000.0000',
            'currency' => 'XOF',
            'direction' => 'outgoing',
            'type' => 'refund',
            'method' => 'bank_transfer',
            'refund_of' => $fixture['paymentId'],
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Le montant ne peut pas changer dans la même requête que la confirmation.
        expect(fn () => DB::table('payments')
            ->where('id', $refundId)
            ->update([
                'status' => 'confirmed',
                'amount' => '500000.0000',
            ])
        )->toThrow(QueryException::class);

        DB::table('payments')
            ->where('id', $refundId)
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('payments')
            ->where('id', $refundId)
            ->update(['amount' => '500000.0000'])
        )->toThrow(QueryException::class);

        expect(
            DB::table('payments')
                ->where('id', $refundId)
                ->value('amount')
        )->toBe('400000.0000');
    });
});
