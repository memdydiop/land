<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->ulid('role_id');
            $table->string('model_type');
            $table->ulid('model_id');

            $table->primary(
                ['role_id', 'model_id', 'model_type'],
                'model_has_roles_primary'
            );

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->index(
                ['model_id', 'model_type'],
                'model_has_roles_model_id_model_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_roles');
    }
};
