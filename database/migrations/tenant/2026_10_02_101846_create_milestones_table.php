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
        Schema::create('milestones', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('project_id');

            $table->string('name');
            $table->text('description')->nullable();

            $table->date('due_date');
            $table->timestampTz('completed_at')->nullable();

            $table->string('status');

            $table->timestampsTz();

            // A milestone belongs strictly to one project.
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index(['project_id', 'status']);
            $table->index('due_date');
        });

        // Domain integrity: milestone status.
        DB::statement("
            ALTER TABLE milestones
            ADD CONSTRAINT milestones_status_check
            CHECK (
                status IN (
                    'planned',
                    'in_progress',
                    'completed',
                    'cancelled'
                )
            )
        ");

        /*
         * A completed milestone must have a completion timestamp.
         * A non-completed milestone must not have one.
         */
        DB::statement("
            ALTER TABLE milestones
            ADD CONSTRAINT milestones_completed_at_check
            CHECK (
                (status = 'completed' AND completed_at IS NOT NULL)
                OR
                (status <> 'completed' AND completed_at IS NULL)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};