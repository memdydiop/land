<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('commercial_offer_id');
            $table->ulid('party_id');

            $table->string('reference');
            $table->string('status');

            $table->timestampTz('reserved_at');
            $table->timestampTz('expires_at')->nullable();

            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);

            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('commercial_offer_id')
                ->references('id')
                ->on('commercial_offers')
                ->restrictOnDelete();

            $table->foreign('party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->index('commercial_offer_id');
            $table->index('party_id');
            $table->index('status');
            $table->index(['party_id', 'status']);
            $table->index('reserved_at');
            $table->index('expires_at');
        });

        DB::statement("
            CREATE UNIQUE INDEX reservations_active_offer_unique
            ON reservations (commercial_offer_id)
            WHERE status IN ('pending', 'confirmed')
        ");

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_amount_check
            CHECK (amount >= 0)
        ");

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_dates_check
            CHECK (
                expires_at IS NULL
                OR expires_at >= reserved_at
            )
        ");

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_status_check
            CHECK (status IN (
                'pending',
                'confirmed',
                'expired',
                'cancelled',
                'converted'
            ))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
