<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['Supported', 'Contradicted', 'Insufficient Evidence', 'Unable to Assess']);
            $table->text('explanation');
            $table->json('cited_page_numbers')->default('[]');
            $table->json('candidate_passages')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_assessments');
    }
};
