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
        Schema::create('construction_sites', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('project_id');

            $table->string('name');
            $table->string('reference');

            $table->string('status');

            $table->ulid('manager_id')->nullable();

            $table->text('address')->nullable();

            $table->geometry('location', 'point', 4326)->nullable();
            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // A construction site belongs strictly to one project.
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            // A manager is optional and may be removed without
            // deleting the construction site.
            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Query indexes.
            $table->index('project_id');
            $table->index('status');
            $table->index('manager_id');
            $table->index('start_date');
            $table->index('expected_end_date');
        });

        // Domain integrity: construction site status.
        DB::statement("
            ALTER TABLE construction_sites
            ADD CONSTRAINT construction_sites_status_check
            CHECK (
                status IN (
                    'planned',
                    'active',
                    'suspended',
                    'completed',
                    'cancelled'
                )
            )
        ");

        // Expected completion cannot precede the start date.
        DB::statement("
            ALTER TABLE construction_sites
            ADD CONSTRAINT construction_sites_expected_end_date_check
            CHECK (
                start_date IS NULL
                OR expected_end_date IS NULL
                OR expected_end_date >= start_date
            )
        ");

        // Actual completion cannot precede the start date.
        DB::statement("
            ALTER TABLE construction_sites
            ADD CONSTRAINT construction_sites_actual_end_date_check
            CHECK (
                start_date IS NULL
                OR actual_end_date IS NULL
                OR actual_end_date >= start_date
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE construction_sites
            ADD CONSTRAINT construction_sites_location_srid_check
            CHECK (
                location IS NULL
                OR ST_SRID(location) = 4326
            )
        ");

        DB::statement("
            ALTER TABLE construction_sites
            ADD CONSTRAINT construction_sites_boundary_srid_check
            CHECK (
                boundary IS NULL
                OR ST_SRID(boundary) = 4326
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_sites');
    }
};