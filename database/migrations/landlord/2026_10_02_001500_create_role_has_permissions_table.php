<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->ulid('permission_id');
            $table->ulid('role_id');

            $table->primary(['permission_id', 'role_id']);

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
    }
};
