<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'Queued';
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_PROCESSED = 'Processed';
    public const STATUS_PARTIAL = 'Partial';
    public const STATUS_FAILED = 'Failed';

    protected $fillable = [
        'project_id',
        'title',
        'original_filename',
        'file_path',
        'file_size',
        'mime_type',
        'page_count',
        'status',
        'error_message',
        'extracted_text',
        'pages_data',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'page_count' => 'integer',
        'pages_data' => 'array',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
