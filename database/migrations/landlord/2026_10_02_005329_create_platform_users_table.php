<?php

declare(strict_types=1);

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
        Schema::create('platform_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('name');

            $table->string('email')->unique();

            $table->string('password');

            $table->boolean('is_active')->default(true);

            $table->timestampTz('last_login_at')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_users');
    }
};
