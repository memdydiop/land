<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('land_operations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('reference');
            $table->string('name');

            $table->string('type');
            $table->string('status');

            $table->ulid('project_id')->nullable();

            $table->text('description')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // A land operation may optionally be associated
            // with a project.
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->nullOnDelete();

            // Query indexes.
            $table->index('type');
            $table->index('status');
            $table->index('project_id');
            $table->index(['status', 'type']);
            $table->index('start_date');
            $table->index('end_date');
        });

        // Domain integrity: land operation type.
        DB::statement("
            ALTER TABLE land_operations
            ADD CONSTRAINT land_operations_type_check
            CHECK (
                type IN (
                    'subdivision',
                    'land_development',
                    'lotissement',
                    'redevelopment',
                    'other'
                )
            )
        ");

        // Domain integrity: land operation status.
        DB::statement("
            ALTER TABLE land_operations
            ADD CONSTRAINT land_operations_status_check
            CHECK (
                status IN (
                    'draft',
                    'study',
                    'administrative',
                    'approved',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'archived'
                )
            )
        ");

        // End date cannot precede start date.
        DB::statement("
            ALTER TABLE land_operations
            ADD CONSTRAINT land_operations_dates_check
            CHECK (
                start_date IS NULL
                OR end_date IS NULL
                OR end_date >= start_date
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('land_operations');
    }
};