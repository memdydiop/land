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
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('operation_id')->nullable();

            $table->string('reference');
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('type');
            $table->string('status');

            $table->ulid('client_party_id')->nullable();
            $table->ulid('manager_id')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->decimal('budget_amount', 20, 4)->nullable();
            $table->char('currency', 3)->nullable();

            $table->geometry('location', 'point', 4326)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // Foreign keys.
            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->nullOnDelete();

            $table->foreign('client_party_id')
                ->references('id')
                ->on('parties')
                ->nullOnDelete();

            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Query indexes.
            $table->index('operation_id');
            $table->index('type');
            $table->index('status');
            $table->index('client_party_id');
            $table->index('manager_id');
            $table->index('start_date');
            $table->index('end_date');
        });

        // Domain integrity: project type.
        DB::statement("
            ALTER TABLE projects
            ADD CONSTRAINT projects_type_check
            CHECK (
                type IN (
                    'construction',
                    'civil_engineering',
                    'development',
                    'land_development',
                    'real_estate',
                    'infrastructure',
                    'other'
                )
            )
        ");

        // Domain integrity: project status.
        DB::statement("
            ALTER TABLE projects
            ADD CONSTRAINT projects_status_check
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
        ");

        // Budget cannot be negative.
        DB::statement("
            ALTER TABLE projects
            ADD CONSTRAINT projects_budget_amount_check
            CHECK (
                budget_amount IS NULL
                OR budget_amount >= 0
            )
        ");

        // End date cannot precede start date.
        DB::statement("
            ALTER TABLE projects
            ADD CONSTRAINT projects_dates_check
            CHECK (
                start_date IS NULL
                OR end_date IS NULL
                OR end_date >= start_date
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE projects
            ADD CONSTRAINT projects_location_srid_check
            CHECK (
                location IS NULL
                OR ST_SRID(location) = 4326
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};