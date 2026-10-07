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
        Schema::create('commercial_offer_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('commercial_offer_id');

            $table->ulid('land_id')->nullable();
            $table->ulid('parcel_id')->nullable();
            $table->ulid('property_id')->nullable();
            $table->ulid('unit_id')->nullable();

            $table->unsignedSmallInteger('position')->default(0);

            $table->timestampsTz();

            $table->foreign('commercial_offer_id')
                ->references('id')
                ->on('commercial_offers')
                ->cascadeOnDelete();

            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->restrictOnDelete();

            $table->foreign('parcel_id')
                ->references('id')
                ->on('parcels')
                ->restrictOnDelete();

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->restrictOnDelete();

            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();

            $table->unique(['commercial_offer_id', 'position']);

            $table->unique(['commercial_offer_id', 'land_id']);
            $table->unique(['commercial_offer_id', 'parcel_id']);
            $table->unique(['commercial_offer_id', 'property_id']);
            $table->unique(['commercial_offer_id', 'unit_id']);

            $table->index('land_id');
            $table->index('parcel_id');
            $table->index('property_id');
            $table->index('unit_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offer_items
            ADD CONSTRAINT commercial_offer_items_target_check
            CHECK (
                num_nonnulls(
                    land_id,
                    parcel_id,
                    property_id,
                    unit_id
                ) = 1
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_offer_items');
    }
};
