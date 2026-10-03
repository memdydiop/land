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
        Schema::create('project_members', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('project_id');
            $table->ulid('user_id');

            $table->string('role');

            $table->timestampsTz();

            // A user can belong only once to the same project.
            $table->unique(['project_id', 'user_id']);

            // A project's members are strictly dependent on the project.
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            // Preserve historical project membership.
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            // Query indexes.
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};