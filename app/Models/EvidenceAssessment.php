<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceAssessment extends Model
{
    protected $fillable = [
        'claim_id',
        'project_id',
        'status',
        'explanation',
        'cited_page_numbers',
        'candidate_passages',
    ];

    protected function casts(): array
    {
        return [
            'cited_page_numbers' => 'array',
            'candidate_passages' => 'array',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
