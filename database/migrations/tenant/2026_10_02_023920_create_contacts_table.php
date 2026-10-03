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
        Schema::create('contacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('type', 20)->default('person');

            $table->string('first_name');
            $table->string('last_name');

            $table->timestampsTz();

            $table->foreign('id')
                ->references('id')
                ->on('parties')
                ->cascadeOnDelete();
        });

        DB::statement(
            "ALTER TABLE contacts
             ADD CONSTRAINT contacts_type_check
             CHECK (type = 'person')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
