<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Claim extends Model
{
    protected $fillable = [
        'project_id',
        'claim_text',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(EvidenceAssessment::class)->latestOfMany();
    }
}
