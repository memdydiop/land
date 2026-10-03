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
        Schema::create('project_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('project_id');

            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('position');
            $table->string('status');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestampsTz();

            // A phase belongs strictly to one project.
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            // Phase ordering within a project.
            $table->unique(['project_id', 'position']);

            // Query indexes.
            $table->index('status');
            $table->index(['project_id', 'status']);
        });

        // Domain integrity: phase status.
        DB::statement("
            ALTER TABLE project_phases
            ADD CONSTRAINT project_phases_status_check
            CHECK (
                status IN (
                    'planned',
                    'in_progress',
                    'completed',
                    'on_hold',
                    'cancelled'
                )
            )
        ");

        // End date cannot precede start date.
        DB::statement("
            ALTER TABLE project_phases
            ADD CONSTRAINT project_phases_dates_check
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
        Schema::dropIfExists('project_phases');
    }
};