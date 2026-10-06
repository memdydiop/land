<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('reference');
            $table->string('name');

            $table->string('type');
            $table->string('status');

            $table->text('description')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestampsTz();

            $table->unique('reference');

            $table->index('type');
            $table->index('status');
            $table->index(['status', 'type']);
            $table->index('start_date');
            $table->index('end_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE operations
            ADD CONSTRAINT operations_type_check
            CHECK (
                type IN (
                    'land_development',
                    'real_estate_development',
                    'construction',
                    'infrastructure',
                    'mixed',
                    'other'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE operations
            ADD CONSTRAINT operations_status_check
            CHECK (
                status IN (
                    'draft',
                    'planned',
                    'active',
                    'on_hold',
                    'completed',
                    'cancelled',
                    'archived'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE operations
            ADD CONSTRAINT operations_dates_check
            CHECK (
                start_date IS NULL
                OR end_date IS NULL
                OR end_date >= start_date
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
