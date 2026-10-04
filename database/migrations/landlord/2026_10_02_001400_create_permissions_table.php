<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
