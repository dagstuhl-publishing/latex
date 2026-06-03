<?php

namespace Dagstuhl\Latex\LatexStructures;

use Illuminate\Support\Facades\Log;

use Dagstuhl\Latex\Utilities\Filesystem;
use DOMDocument;

class PdfFile
{
    private bool $runningsChecked = false;
    private bool $authorRunningFits = true;
    private bool $titleRunningFits = true;

    private ?LatexFile $latexFile = NULL;
    private int $pageCount = -1;

    public function __construct(private string $path, ?LatexFile $latexFile = NULL)
    {
        if (!Filesystem::fileExists($path)) {
            throw new \InvalidArgumentException("PDF does not exist: {$path}");
        }

        $this->latexFile = $latexFile;
    }

    public static function fromLatexFile(LatexFile $latexFile): ?self
    {
        try {
            return new self($latexFile->getPath('pdf'), $latexFile);
        } catch (\InvalidArgumentException $e) {
            Log::error($e->getMessage());
            return NULL;
        }
    }

    public function getLatexFile(): ?LatexFile
    {
        return $this->latexFile;
    }

    public function getPath(string $extension = 'pdf'): string
    {
        $originalExtension = '.'.pathinfo($this->path, PATHINFO_EXTENSION);
        $path = $this->getDirectory() . $this->getFilename();

        return preg_replace('/'.preg_quote($originalExtension).'$/', '.'.$extension, $path);
    }

    public function getFilename(): string
    {
        return pathinfo($this->path, PATHINFO_BASENAME);
    }

    public function getDirectory(): string
    {
        return pathinfo($this->path, PATHINFO_DIRNAME).'/';
    }

    public function getNumberOfPages(): int
    {
        if ($this->pageCount < 0) {
            if (!function_exists('config')) {
                Log::error("Function 'config' does not exist, but is needed to find the pdfinfo binary.");
                return 0;
            }

            $cmd = config('latex.paths.pdf-info-bin').' "'.Filesystem::storagePath($this->path).'"';

            exec ($cmd, $output);

            foreach($output as $op) {
                if (preg_match("/Pages:\s*(\d+)/i", $op, $matches) === 1) {
                    $this->pageCount = intval($matches[1]);
                    break;
                }
            }
        }

        return $this->pageCount;
    }


    public function authorRunningFits(): bool
    {
        $this->assertRunningsChecked();
        return $this->authorRunningFits;
    }

    public function titleRunningFits(): bool
    {
        $this->assertRunningsChecked();
        return $this->titleRunningFits;
    }

    private function assertRunningsChecked() {
        if (!$this->runningsChecked) {
            $this->runningsChecked = true;

            $marginTolerance = 1.0;

            function analyzePageSet(array $words, float $marginTolerance): bool
            {
                $ignoredWordRegex = '/^\d+\s*:\s*\d+$/';

                $xMinCounts = [];
                $xMaxCounts = [];

                $smallestYMin = INF;
                $headerMargin = null;

                foreach ($words as $word) {
                    $xMin = $word['xMin'];
                    $xMax = $word['xMax'];
                    $yMin = $word['yMin'];
                    $yMax = $word['yMax'];

                    $xMinCounts[$xMin] = ($xMinCounts[$xMin] ?? 0) + 1;
                    $xMaxCounts[$xMax] = ($xMaxCounts[$xMax] ?? 0) + 1;

                    if ($yMin < $smallestYMin) {
                        $smallestYMin = $yMin;
                        $headerMargin = $yMax;
                        $topWord = $word['text'];
                    }
                }

                arsort($xMinCounts);
                arsort($xMaxCounts);

                $leftMargin  = (float)array_key_first($xMinCounts);
                $rightMargin = (float)array_key_first($xMaxCounts);

                foreach ($words as $word) {
                    if (preg_match($ignoredWordRegex, $word['text'])) {
                        continue;
                    }

                    if (
                        (
                            $word['xMin'] < $leftMargin - $marginTolerance &&
                            $word['yMin'] < $headerMargin
                        )
                        ||
                        (
                            $word['xMax'] > $rightMargin + $marginTolerance &&
                            $word['yMin'] < $headerMargin
                        )
                    ) {
                        return false;
                    }
                }

                return true;
            }

            if (!function_exists('config')) {
                Log::error("Function 'config' does not exist, but is needed to find the pdftotext binary.");
                return;
            }

            $pdftotextBin = config('latex.paths.pdf-to-text-bin');
            if (!is_executable($pdftotextBin)) {
                Log::error("Need pdftotext binary, but got this non-executable: $pdftotextBin");
                return;
            }

            $cmd = sprintf('%s -bbox %s -', $pdftotextBin, escapeshellarg($this->path));

            $descriptors = [
                1 => ['pipe', 'w'], // stdout (XML output)
                2 => ['pipe', 'w'], // stderr
            ];

            $process = proc_open($cmd, $descriptors, $pipes);

            if (!is_resource($process)) {
                Log::error("Failed to run pdftotext on file '$this->path'.");
                return;
            }

            $xml = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $status = proc_close($process);

            if ($status !== 0) {
                Log::error("Process pdftotext finished with non-zero return status.");
                return;
            }

            // Remove invalid XML control characters
            $xml = preg_replace(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
                '',
                $xml
            );

            libxml_use_internal_errors(true);

            $dom = new DOMDocument();

            if (!$dom->loadXML($xml)) {
                $xmlErrors = "Failed to parse XML output of pdftotext:";
                foreach (libxml_get_errors() as $error) {
                    $xmlErrors .= "\n- $error->message";
                }
                Log::error($xmlErrors);
                return;
            }

            $pages = $dom->getElementsByTagName('page');

            $this->pageCount = $pages->length;
            if ($this->pageCount === 0) {
                Log::error("No pages found in PDF document.");
                return;
            }

            // treat odd and even pages separately
            $oddWords = [];
            $evenWords = [];

            foreach ($pages as $pageIndex => $page) {
                if ($pageIndex == 0) continue;

                if ($pageIndex % 2 == 0) {
                    $words = &$oddWords;
                } else {
                    $words = &$evenWords;
                }

                $wordNodes = $page->getElementsByTagName('word');

                foreach ($wordNodes as $word) {
                    $oddEvenWord = [
                        'text' => trim($word->textContent),
                        'xMin' => round((float)$word->getAttribute('xMin')),
                        'yMin' => round((float)$word->getAttribute('yMin')),
                        'xMax' => round((float)$word->getAttribute('xMax')),
                        'yMax' => round((float)$word->getAttribute('yMax')),
                    ];

                    $words[] = $oddEvenWord;
                }
            }

            $this->authorRunningFits = analyzePageSet($oddWords, $marginTolerance);
            $this->titleRunningFits = analyzePageSet($evenWords, $marginTolerance);
        }
    }
}
