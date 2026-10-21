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
        Schema::create('references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_key')->nullable();
            $table->string('type')->default('article-journal');
            $table->text('title');
            $table->json('authors')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('publication_title')->nullable();
            $table->string('volume')->nullable();
            $table->string('issue')->nullable();
            $table->string('pages')->nullable();
            $table->string('publisher')->nullable();
            $table->string('publisher_place')->nullable();
            $table->string('doi')->nullable();
            $table->string('url')->nullable();
            $table->json('csl_data')->nullable();
            $table->boolean('is_confirmed')->default(true);
            $table->timestamps();

            $table->index(['project_id', 'doi']);
            $table->index(['project_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('references');
    }
};
