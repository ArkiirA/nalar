<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DocumentChunk extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentChunkFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'document_id',
        'document_page_id',
        'page_number',
        'chunk_index',
        'content',
        'token_count',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'chunk_index' => 'integer',
            'token_count' => 'integer',
        ];
    }

    /**
     * Cast pgvector embedding to and from PHP array of floats.
     */
    protected function embedding(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) {
                    return null;
                }
                if (is_array($value)) {
                    return array_map('floatval', $value);
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return array_map('floatval', $decoded);
                    }
                    $trimmed = trim($value, '[]');
                    if ($trimmed === '') {
                        return [];
                    }

                    return array_map('floatval', explode(',', $trimmed));
                }

                return $value;
            },
            set: function ($value) {
                if ($value === null) {
                    return null;
                }
                if (is_array($value)) {
                    return json_encode(array_values(array_map('floatval', $value)));
                }

                return $value;
            }
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(DocumentPage::class, 'document_page_id');
    }

    /**
     * Query candidate passages by cosine similarity using pgvector with strict project scoping.
     */
    public function scopeNearestTo(Builder $query, int $projectId, array|string $vector, int $limit = 5): Builder
    {
        $vectorLiteral = is_array($vector) ? json_encode(array_values($vector)) : $vector;

        $query->where('document_chunks.project_id', $projectId);

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->selectRaw('document_chunks.*, 1 - (embedding <=> ?) AS similarity', [$vectorLiteral])
                ->orderByRaw('embedding <=> ? ASC', [$vectorLiteral])
                ->limit($limit);
        }

        return $query->limit($limit);
    }
}
