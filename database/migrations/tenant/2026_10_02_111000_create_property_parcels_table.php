<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_parcels', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('property_id');
            $table->ulid('parcel_id');

            $table->timestampsTz();

            $table->unique([
                'property_id',
                'parcel_id',
            ]);

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->cascadeOnDelete();

            $table->foreign('parcel_id')
                ->references('id')
                ->on('parcels')
                ->restrictOnDelete();

            $table->index('parcel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_parcels');
    }
};
