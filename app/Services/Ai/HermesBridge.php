<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class HermesBridge
{
    /**
     * Hermes executable path or fallback command.
     */
    protected string $executable;

    public function __construct(?string $executable = null)
    {
        $this->executable = $executable 
            ?: env('HERMES_BIN_PATH', 'hermes');
    }

    /**
     * Query Hermes agent in one-shot mode with thesis context.
     */
    public function query(string $prompt, ?string $chapterTitle = null, ?string $chapterText = null): string
    {
        $context = '';

        if (!empty($chapterTitle)) {
            $context .= "Konteks Bab Skripsi: {$chapterTitle}\n";
        }

        if (!empty($chapterText)) {
            // Trim to prevent overwhelming CLI buffer
            $trimmed = mb_substr(trim($chapterText), 0, 3000);
            $context .= "Draf Teks Saat Ini:\n\"\"\"\n{$trimmed}\n\"\"\"\n\n";
        }

        $fullPrompt = $context 
            . "Peran Anda: Asisten Riset & Penulisan Skripsi Akademik Indonesia (Nalar Hermes Copilot).\n"
            . "Pertanyaan/Instruksi Mahasiswa: {$prompt}";

        // Windows execution fallback
        $binary = $this->resolveBinary();

        $result = Process::timeout(120)->run([$binary, '-z', $fullPrompt]);

        if ($result->failed()) {
            throw new RuntimeException("Hermes agent error: " . ($result->error() ?: $result->output()));
        }

        // ponytail: one-shot CLI execution; upgrade to persistent local hermes daemon/websocket when low latency (<500ms) is required
        return trim($result->output());
    }

    /**
     * Resolve binary path on Windows/Unix.
     */
    protected function resolveBinary(): string
    {
        if (file_exists($this->executable)) {
            return $this->executable;
        }

        $windowsPath = 'C:\\Users\\HP\\AppData\\Local\\hermes\\bin\\hermes.cmd';
        if (file_exists($windowsPath)) {
            return $windowsPath;
        }

        return 'hermes';
    }
}
