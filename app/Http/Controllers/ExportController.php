<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DocxExportService;
use App\Services\ExportIntegrityValidator;
use App\Services\PdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportIntegrityValidator $validator,
        protected DocxExportService $docxService,
        protected PdfExportService $pdfService,
    ) {}

    /**
     * Run pre-export integrity validation check across project chapters and references.
     */
    public function validateExport(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $report = $this->validator->validate($project);

        return response()->json($report);
    }

    /**
     * Compile thesis chapters into a formatted DOCX document and stream download.
     */
    public function exportDocx(Request $request, Project $project): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        $filePath = $this->docxService->generate($project);
        $slug = Str::slug($project->title) ?: 'skripsi';
        $downloadName = "{$slug}-draft.docx";

        return response()->download($filePath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Compile thesis chapters into a campus-styled PDF document and stream download.
     */
    public function exportPdf(Request $request, Project $project): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        $filePath = $this->pdfService->generate($project);
        $slug = Str::slug($project->title) ?: 'skripsi';
        $downloadName = "{$slug}-naskah.pdf";

        return response()->download($filePath, $downloadName, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}
