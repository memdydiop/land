<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('document_id');
            $table->unsignedInteger('version');
            $table->string('status');
            $table->string('disk');
            $table->text('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 128);
            $table->ulid('uploaded_by');
            $table->timestampTz('created_at');

            $table->unique(['document_id', 'version']);

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('uploaded_by');
            $table->index('status');
        });

        DB::statement("ALTER TABLE document_versions ADD CONSTRAINT document_versions_version_check CHECK (version > 0)");
        DB::statement("ALTER TABLE document_versions ADD CONSTRAINT document_versions_size_check CHECK (size >= 0)");
        DB::statement("ALTER TABLE document_versions ADD CONSTRAINT document_versions_status_check CHECK (status IN ('active','superseded','archived'))");

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('document_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('document_versions');
    }
};
