<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id');

            $table->string('name');
            $table->string('credential_id', 1024)->unique();
            $table->jsonb('credential');
            $table->timestampTz('last_used_at')->nullable();

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
