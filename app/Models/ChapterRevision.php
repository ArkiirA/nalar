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
        'type',
        'content',
        'word_count',
        'created_at',
        'updated_at',
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
            'word_count' => 'integer',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Helper to compute word count from Tiptap JSON structure.
     *
     * @param array<string, mixed>|null $content
     */
    public static function extractWordCount(?array $content): int
    {
        if (empty($content)) {
            return 0;
        }

        $text = self::extractPlainText($content);
        $trimmed = trim($text);

        if ($trimmed === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Recursively extract plain text from Tiptap node tree.
     *
     * @param array<string, mixed> $node
     */
    private static function extractPlainText(array $node): string
    {
        $text = '';

        if (isset($node['text']) && is_string($node['text'])) {
            $text .= $node['text'] . ' ';
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) {
                if (is_array($child)) {
                    $text .= self::extractPlainText($child);
                }
            }
        }

        return $text;
    }
}
