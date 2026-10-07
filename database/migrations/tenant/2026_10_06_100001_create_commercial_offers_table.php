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
        Schema::create('commercial_offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('reference');
            $table->string('title');
            $table->text('description')->nullable();

            $table->ulid('parcel_id')->nullable();
            $table->ulid('unit_id')->nullable();

            $table->decimal('price', 20, 4);
            $table->char('currency', 3);
            $table->string('status');

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('parcel_id')
                ->references('id')
                ->on('parcels')
                ->restrictOnDelete();

            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();

            $table->index('parcel_id');
            $table->index('unit_id');
            $table->index('status');
            $table->index(['status', 'parcel_id']);
            $table->index(['status', 'unit_id']);
            $table->index('valid_from');
            $table->index('valid_until');
        });

        DB::statement("
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_target_check
            CHECK (
                (parcel_id IS NOT NULL AND unit_id IS NULL)
                OR
                (parcel_id IS NULL AND unit_id IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_price_check
            CHECK (price >= 0)
        ");

        DB::statement("
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_dates_check
            CHECK (
                valid_from IS NULL
                OR valid_until IS NULL
                OR valid_until >= valid_from
            )
        ");

        DB::statement("
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_status_check
            CHECK (status IN (
                'draft',
                'active',
                'reserved',
                'sold',
                'expired',
                'withdrawn'
            ))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_offers');
    }
};
