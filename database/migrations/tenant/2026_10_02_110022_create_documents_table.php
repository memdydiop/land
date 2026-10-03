<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('category_id')->nullable();
            $table->string('documentable_type');
            $table->ulid('documentable_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->ulid('current_version_id')->nullable();
            $table->string('visibility');
            $table->ulid('created_by');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('category_id')->references('id')->on('document_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('category_id');
            $table->index('visibility');
            $table->index('created_by');
            $table->index('current_version_id');
        });

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_visibility_check CHECK (visibility IN ('private','internal','restricted'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
