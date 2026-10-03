<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('name');
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');

            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();

            $table->rememberToken();

            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();

            $table->timestampsTz();
        });

        // Email addresses are normalized to lowercase by the application.
        // The database constraint guarantees that non-normalized values cannot
        // be introduced by imports, jobs, SQL scripts, or other write paths.
        DB::statement(
            'ALTER TABLE users
             ADD CONSTRAINT users_email_lowercase_check
             CHECK (email = lower(email))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
