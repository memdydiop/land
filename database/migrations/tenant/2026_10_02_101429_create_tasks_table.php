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
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('project_id')->nullable();
            $table->ulid('phase_id')->nullable();
            $table->ulid('parent_id')->nullable();
            $table->ulid('assigned_to')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status');
            $table->string('priority');

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->unsignedInteger('position');

            $table->timestampsTz();
            $table->softDeletesTz();

            /*
             * A task may belong to a project or exist independently.
             * If project_id is set, phase_id may optionally associate
             * the task with one phase of that project.
             */
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->nullOnDelete();

            $table->foreign('phase_id')
                ->references('id')
                ->on('project_phases')
                ->nullOnDelete();

            /*
             * Self-referencing hierarchy:
             *
             * Task
             * ├── Parent task
             * └── Child task
             */
            /* $table->foreign('parent_id')
                ->references('id')
                ->on('tasks')
                ->nullOnDelete(); */

            /*
             * The user may be deactivated without deleting
             * historical task assignments.
             */
            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Task ordering within a project.
            $table->index(['project_id', 'position']);

            // Task ordering within a phase.
            $table->index(['phase_id', 'position']);

            // Hierarchical task queries.
            $table->index('parent_id');

            // Assignment and filtering.
            $table->index('assigned_to');
            $table->index('status');
            $table->index('priority');
            $table->index('due_date');
        });
        Schema::table('tasks', function (Blueprint $table) {
    $table->foreign('parent_id')
        ->references('id')
        ->on('tasks')
        ->nullOnDelete();
});

        // Domain integrity: task status.
        DB::statement("
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_status_check
            CHECK (
                status IN (
                    'todo',
                    'in_progress',
                    'blocked',
                    'completed',
                    'cancelled'
                )
            )
        ");

        // Domain integrity: task priority.
        DB::statement("
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_priority_check
            CHECK (
                priority IN (
                    'low',
                    'normal',
                    'high',
                    'urgent'
                )
            )
        ");

        // Position must always be non-negative.
        DB::statement("
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_position_check
            CHECK (position >= 0)
        ");

        // Due date cannot precede start date.
        DB::statement("
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_dates_check
            CHECK (
                start_date IS NULL
                OR due_date IS NULL
                OR due_date >= start_date
            )
        ");

        /*
         * A completed task must have a completion timestamp.
         * A non-completed task must not have one.
         */
        DB::statement("
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_completed_at_check
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
        Schema::dropIfExists('tasks');
    }
};