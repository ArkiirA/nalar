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
        Schema::create('chapter_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index(['chapter_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_revisions');
    }
};
