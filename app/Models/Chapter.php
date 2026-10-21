<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    /** @use HasFactory<\Database\Factories\ChapterFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'title',
        'content',
        'order',
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
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'parent_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Chapter::class, 'parent_id')->orderBy('order');
    }

    public const MAX_REVISIONS = 50;
    public const AUTO_SNAPSHOT_INTERVAL_MINUTES = 5;

    public function revisions(): HasMany
    {
        return $this->hasMany(ChapterRevision::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Record a snapshot revision for this chapter.
     */
    public function recordSnapshot(string $title = 'Snapshot Otomatis', bool $isManual = false, ?array $content = null): ChapterRevision
    {
        $contentToSnapshot = $content ?? $this->content;

        if ($isManual) {
            $latest = $this->revisions()->first();
            if ($latest && ! $latest->is_manual && $latest->content === $contentToSnapshot && $latest->created_at->gt(now()->subSeconds(15))) {
                $latest->update([
                    'title' => $title,
                    'is_manual' => true,
                ]);

                return $latest->fresh();
            }
        }

        $revision = $this->revisions()->create([
            'title' => $title,
            'content' => $contentToSnapshot,
            'is_manual' => $isManual,
        ]);

        $this->pruneExcessRevisions();

        return $revision;
    }

    /**
     * Restore chapter document content to the specified revision snapshot.
     */
    public function restoreRevision(ChapterRevision $revision): void
    {
        $this->update([
            'content' => $revision->content,
        ]);
    }

    /**
     * Record an automatic background snapshot if eligible (every 5 minutes during active edits).
     */
    public function maybeRecordAutoSnapshot(?array $content = null): ?ChapterRevision
    {
        $contentToSave = $content ?? $this->content;

        if (empty($contentToSave)) {
            return null;
        }

        $lastSnapshot = $this->revisions()->first();

        if ($lastSnapshot && $lastSnapshot->created_at->gt(now()->subMinutes(self::AUTO_SNAPSHOT_INTERVAL_MINUTES))) {
            return null;
        }

        if ($lastSnapshot && $lastSnapshot->content === $contentToSave) {
            return null;
        }

        return $this->recordSnapshot(
            title: 'Snapshot Otomatis',
            isManual: false,
            content: $contentToSave
        );
    }

    /**
     * Prune snapshots exceeding the cap limit (50 snapshots), prioritizing auto snapshots.
     */
    public function pruneExcessRevisions(): int
    {
        $count = $this->revisions()->count();

        if ($count <= self::MAX_REVISIONS) {
            return 0;
        }

        $excess = $count - self::MAX_REVISIONS;

        // First attempt to prune oldest auto snapshots to protect manual checkpoints
        $autoSnapshotIds = $this->revisions()
            ->reorder()
            ->where('is_manual', false)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($excess)
            ->pluck('id');

        $deletedCount = ChapterRevision::whereIn('id', $autoSnapshotIds)->delete();
        $remainingExcess = $excess - $deletedCount;

        if ($remainingExcess > 0) {
            $oldestRemainingIds = $this->revisions()
                ->reorder()
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($remainingExcess)
                ->pluck('id');

            $deletedCount += ChapterRevision::whereIn('id', $oldestRemainingIds)->delete();
        }

        return $deletedCount;
    }
}
