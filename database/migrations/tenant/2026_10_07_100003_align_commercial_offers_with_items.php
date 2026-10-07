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
        // Preserve existing mono-target offers before removing the legacy columns.
        $offers = DB::table('commercial_offers')
            ->select([
                'id',
                'parcel_id',
                'unit_id',
            ])
            ->get();

        foreach ($offers as $offer) {
            if ($offer->parcel_id !== null) {
                DB::table('commercial_offer_items')->insert([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'commercial_offer_id' => $offer->id,
                    'parcel_id' => $offer->parcel_id,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($offer->unit_id !== null) {
                DB::table('commercial_offer_items')->insert([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'commercial_offer_id' => $offer->id,
                    'unit_id' => $offer->unit_id,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // The commercial state is now derived from reservations/sales.
        DB::table('commercial_offers')
            ->where('status', 'reserved')
            ->update(['status' => 'active']);

        DB::table('commercial_offers')
            ->where('status', 'sold')
            ->update(['status' => 'withdrawn']);

        DB::statement(
            'ALTER TABLE commercial_offers DROP CONSTRAINT IF EXISTS commercial_offers_target_check'
        );

        DB::statement(
            'ALTER TABLE commercial_offers DROP CONSTRAINT IF EXISTS commercial_offers_status_check'
        );

        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->dropForeign(['parcel_id']);
            $table->dropForeign(['unit_id']);

            $table->dropIndex(['parcel_id']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['status', 'parcel_id']);
            $table->dropIndex(['status', 'unit_id']);

            $table->dropColumn([
                'parcel_id',
                'unit_id',
            ]);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_status_check
            CHECK (
                status IN (
                    'draft',
                    'active',
                    'expired',
                    'withdrawn'
                )
            )
            SQL);
    }

    public function down(): void
    {
        $nonReversible = DB::table('commercial_offer_items')
            ->select('commercial_offer_id')
            ->groupBy('commercial_offer_id')
            ->havingRaw('COUNT(*) <> 1')
            ->exists();

        $unsupportedTargets = DB::table('commercial_offer_items')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('land_id')
                    ->orWhereNotNull('property_id');
            })
            ->exists();

        if ($nonReversible || $unsupportedTargets) {
            throw new RuntimeException(
                'Cannot reverse commercial offer itemization while multi-item, land, or property offers exist.'
            );
        }

        DB::statement(
            'ALTER TABLE commercial_offers DROP CONSTRAINT IF EXISTS commercial_offers_status_check'
        );

        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->ulid('parcel_id')->nullable();
            $table->ulid('unit_id')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE commercial_offers AS offers
            SET parcel_id = items.parcel_id,
                unit_id = items.unit_id
            FROM commercial_offer_items AS items
            WHERE items.commercial_offer_id = offers.id
            SQL);

        Schema::table('commercial_offers', function (Blueprint $table): void {
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
            $table->index(['status', 'parcel_id']);
            $table->index(['status', 'unit_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_target_check
            CHECK (
                (parcel_id IS NOT NULL AND unit_id IS NULL)
                OR
                (parcel_id IS NULL AND unit_id IS NOT NULL)
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_status_check
            CHECK (
                status IN (
                    'draft',
                    'active',
                    'reserved',
                    'sold',
                    'expired',
                    'withdrawn'
                )
            )
            SQL);

        Schema::dropIfExists('commercial_offer_items');
    }
};
