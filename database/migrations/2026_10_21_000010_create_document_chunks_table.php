<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
        }

        Schema::create('document_chunks', function (Blueprint $table) use ($isPgsql) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_page_id')->nullable()->constrained('document_pages')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('content');
            $table->unsignedInteger('token_count')->default(0);
            if (! $isPgsql) {
                $table->text('embedding')->nullable();
            }
            $table->timestamps();

            $table->index(['project_id', 'document_id']);
            $table->index(['document_id', 'page_number']);
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(768);');
            DB::statement('CREATE INDEX document_chunks_embedding_hnsw_idx ON document_chunks USING hnsw (embedding vector_cosine_ops);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
