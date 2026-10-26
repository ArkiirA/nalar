<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\PdfExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompilePdfExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project,
        public ?string $outputPath = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(PdfExportService $pdfService): string
    {
        return $pdfService->generate($this->project, $this->outputPath);
    }
}
