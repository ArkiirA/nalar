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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type')->default('application/pdf');
            $table->integer('page_count')->default(0);
            $table->string('status')->default('Queued'); // Queued, Processing, Processed, Partial, Failed
            $table->text('error_message')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('pages_data')->nullable(); // [{'page': 1, 'text': '...', 'word_count': 100}]
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
