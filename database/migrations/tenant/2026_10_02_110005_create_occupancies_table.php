<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occupancies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('unit_id');
            $table->ulid('party_id');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestampsTz();

            $table->foreign('unit_id')
                ->references('id')->on('units')->cascadeOnDelete();

            $table->foreign('party_id')
                ->references('id')->on('parties')->restrictOnDelete();

            $table->index(['unit_id', 'start_date']);
            $table->index('party_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE occupancies ADD CONSTRAINT occupancies_dates_check CHECK (end_date IS NULL OR end_date >= start_date)");
        DB::statement("ALTER TABLE occupancies ADD CONSTRAINT occupancies_amount_check CHECK (amount IS NULL OR amount >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('occupancies');
    }
};
