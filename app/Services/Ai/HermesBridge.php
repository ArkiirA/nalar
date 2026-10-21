<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class HermesBridge
{
    /**
     * Hermes executable path or fallback command.
     */
    protected ?string $configuredBin;

    public function __construct(?string $configuredBin = null)
    {
        $this->configuredBin = $configuredBin ?: env('HERMES_BIN_PATH');
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
            . "Peran Anda: Asisten Riset dan Penulisan Skripsi Akademik Indonesia (Nalar Hermes Copilot).\n"
            . "Pertanyaan/Instruksi Mahasiswa: {$prompt}";

        $command = $this->buildCommand($fullPrompt);

        $result = Process::timeout(180)
            ->env($this->resolveEnvironment())
            ->run($command);

        if ($result->failed()) {
            $msg = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException("Hermes agent error (code {$result->exitCode()}): {$msg}");
        }

        // ponytail: one-shot CLI execution; upgrade to persistent local hermes daemon/websocket when low latency (<500ms) is required
        return trim($result->output());
    }

    /**
     * Resolve environment variables needed for Hermes runtime (especially in web server contexts).
     */
    public function resolveEnvironment(): array
    {
        $userProfile = env('USERPROFILE', getenv('USERPROFILE') ?: 'C:\\Users\\HP');
        $hermesHome = env('HERMES_HOME', getenv('HERMES_HOME') ?: ($userProfile . '\\AppData\\Local\\hermes'));

        return array_filter([
            'USERPROFILE' => $userProfile,
            'HOME' => $userProfile,
            'HERMES_HOME' => $hermesHome,
            'LOCALAPPDATA' => $userProfile . '\\AppData\\Local',
            'APPDATA' => $userProfile . '\\AppData\\Roaming',
            'PATH' => getenv('PATH') ?: null,
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
        ]);
    }

    /**
     * Build command array avoiding Windows batch file (.cmd) ampersand/pipe parsing pitfalls.
     */
    public function buildCommand(string $prompt): array
    {
        if ($this->configuredBin) {
            return [$this->configuredBin, '-z', $prompt];
        }

        // On Windows with local embedded Hermes, invoke Python directly to avoid cmd.exe batch parsing
        $windowsPython = 'C:\\Users\\HP\\AppData\\Local\\hermes\\tools\\python-3.14.7+20260901-win32-x64\\python.exe';
        $windowsAgent = 'C:\\Users\\HP\\AppData\\Local\\hermes\\hermes-agent';
        $userProfile = env('USERPROFILE', getenv('USERPROFILE') ?: 'C:\\Users\\HP');
        $hermesRoot = env('HERMES_HOME', getenv('HERMES_HOME') ?: ($userProfile . '\\AppData\\Local\\hermes'));

        if (PHP_OS_FAMILY === 'Windows' && file_exists($windowsPython) && is_dir($windowsAgent)) {
            $bootstrap = 'import os, sys; '
                . 'os.environ["HERMES_HOME"] = r"' . $hermesRoot . '"; '
                . 'os.environ["USERPROFILE"] = r"' . $userProfile . '"; '
                . 'os.environ["HOME"] = r"' . $userProfile . '"; '
                . 'os.environ["LOCALAPPDATA"] = r"' . $userProfile . '\\AppData\\Local"; '
                . 'os.environ["APPDATA"] = r"' . $userProfile . '\\AppData\\Roaming"; '
                . 'sys.path.insert(0, r"' . $windowsAgent . '"); '
                . 'from hermes_cli.main import main; sys.exit(main())';

            return [
                $windowsPython,
                '-c',
                $bootstrap,
                '-z',
                $prompt,
            ];
        }

        $windowsCmd = 'C:\\Users\\HP\\AppData\\Local\\hermes\\bin\\hermes.cmd';
        if (file_exists($windowsCmd)) {
            return [$windowsCmd, '-z', $prompt];
        }

        return ['hermes', '-z', $prompt];
    }
}
