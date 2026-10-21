<?php

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
        $connection = Schema::getConnection();
        $isPgsql = $connection->getDriverName() === 'pgsql';

        if ($isPgsql) {
            $connection->statement("ALTER TABLE document_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('indonesian', coalesce(content, '')) || to_tsvector('english', coalesce(content, ''))) STORED;");
            $connection->statement('CREATE INDEX document_chunks_search_vector_gin_idx ON document_chunks USING gin (search_vector);');
        } else {
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->text('search_vector')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();
        $isPgsql = $connection->getDriverName() === 'pgsql';

        if ($isPgsql) {
            $connection->statement('DROP INDEX IF EXISTS document_chunks_search_vector_gin_idx;');
            $connection->statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS search_vector;');
        } else {
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->dropColumn('search_vector');
            });
        }
    }
};
