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
        Schema::create('parties', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // person | company
            $table->string('type', 20);
            $table->string('display_name', 255);

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            // Required so subtype tables can reference (id, type).
            $table->unique(['id', 'type']);

            $table->index('email');
            $table->index('phone');
        });

        // Keep this CHECK synchronized with the PartyType PHP backed enum.
        DB::statement(
            "ALTER TABLE parties
             ADD CONSTRAINT parties_type_check
             CHECK (type IN ('person', 'company'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
