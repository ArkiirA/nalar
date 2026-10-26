<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupervisionNote extends Model
{
    /** @use HasFactory<\Database\Factories\SupervisionNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'meeting_date',
        'advisor_name',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(SupervisionTask::class)->orderBy('id');
    }

    public function pendingTasks(): HasMany
    {
        return $this->tasks()->where('is_completed', false);
    }
}
