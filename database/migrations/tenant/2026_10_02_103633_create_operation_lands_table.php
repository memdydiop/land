<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_lands', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('operation_id');
            $table->ulid('land_id');

            $table->timestampsTz();

            $table->unique([
                'operation_id',
                'land_id',
            ]);

            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->cascadeOnDelete();

            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->cascadeOnDelete();

            $table->index('land_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_lands');
    }
};
