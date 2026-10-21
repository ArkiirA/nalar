<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id',
        'title',
        'content',
        'is_manual',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_manual' => 'boolean',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Array representation for index listing without heavy content payload.
     *
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'chapter_id' => $this->chapter_id,
            'title' => $this->title,
            'is_manual' => (bool) $this->is_manual,
            'created_at' => $this->created_at->toIso8601String(),
            'relative_time' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Full array representation including structured content.
     *
     * @return array<string, mixed>
     */
    public function toFullArray(): array
    {
        return [
            ...$this->toSummaryArray(),
            'content' => $this->content,
        ];
    }
}
