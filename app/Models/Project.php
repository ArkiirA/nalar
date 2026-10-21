<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'target_completion_date',
        'guideline_preset',
        'guideline_profile',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_completion_date' => 'date:Y-m-d',
            'guideline_profile' => 'array',
        ];
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('order');
    }

    public function rootChapters(): HasMany
    {
        return $this->chapters()->whereNull('parent_id');
    }

    public function outlineItems(): HasMany
    {
        return $this->chapters();
    }

    public function allChapters(): HasMany
    {
        return $this->chapters();
    }

    /**
     * Apply a guideline preset to the project and populate default chapters and sections.
     */
    public function applyGuidelinePreset(string $presetKey): void
    {
        \App\Support\GuidelinePresets::applyToProject($this, $presetKey);
    }
}
