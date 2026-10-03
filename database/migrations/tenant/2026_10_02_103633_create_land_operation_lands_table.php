<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('land_operation_lands', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('land_operation_id');
            $table->ulid('land_id');

            $table->timestampsTz();

            // A land operation may reference the same land only once.
            $table->unique([
                'land_operation_id',
                'land_id',
            ]);

            // The association strictly depends on the operation.
            $table->foreign('land_operation_id')
                ->references('id')
                ->on('land_operations')
                ->cascadeOnDelete();

            // The association strictly depends on the land.
            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->cascadeOnDelete();

            // Useful for reverse lookups from a land.
            $table->index('land_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('land_operation_lands');
    }
};