<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionTask extends Model
{
    /** @use HasFactory<\Database\Factories\SupervisionTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'supervision_note_id',
        'chapter_id',
        'title',
        'is_completed',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function toggle(): bool
    {
        $this->is_completed = ! $this->is_completed;
        $this->completed_at = $this->is_completed ? now() : null;
        $this->save();

        return $this->is_completed;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisionNote(): BelongsTo
    {
        return $this->belongsTo(SupervisionNote::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
