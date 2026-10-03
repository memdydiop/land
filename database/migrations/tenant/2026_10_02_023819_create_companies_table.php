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
        Schema::create('companies', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Technical discriminator used to enforce the subtype invariant.
            $table->string('type', 20)->default('company');

            $table->string('legal_name');
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('website')->nullable();

            $table->timestampsTz();

            $table->foreign('id')
                ->references('id')
                ->on('parties')
                ->cascadeOnDelete();

            $table->unique('registration_number');
            $table->unique('tax_number');
        });

        DB::statement(
            "ALTER TABLE companies
             ADD CONSTRAINT companies_type_check
             CHECK (type = 'company')"
        );

        DB::statement(
            "ALTER TABLE companies
             ADD CONSTRAINT companies_identifiers_not_blank_check
             CHECK (
                 (registration_number IS NULL OR btrim(registration_number) <> '')
                 AND (tax_number IS NULL OR btrim(tax_number) <> '')
             )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
