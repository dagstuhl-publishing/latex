<?php

namespace Dagstuhl\Latex\Compiler\CompilationProfiles\PdfLatexBibtexLocal;

use Dagstuhl\Latex\Compiler\CompilationProfiles\CompilationProfileInterface;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use Exception;

class PdfLatexBibtexLocalProfile implements CompilationProfileInterface
{
    protected LatexFile $latexFile;
    
    protected ?string $version = NULL;
    protected ?int $latexExitCode;
    protected ?int $bibtexExitCode;

    protected array $profileOutput = [];

    const MODE_FULL = 'full';
    const MODE_LATEX_ONLY = 'latex-only';
    const MODE_BIBTEX_ONLY = 'bibtex-only';

    public function __construct(LatexFile $latexFile)
    {
        $this->latexFile = $latexFile;
    }

    private function getProfileCommand(): string
    {
        return __DIR__.'/pdflatex-bibtex-local.sh';
    }

    public function getLatexVersion(): string
    {
        $this->setEnvironmentVariables();
        $command = $this->getProfileCommand(). ' --version';
        exec($command, $out);

        return $out[0] ?? 'pdflatex';
    }

    private function getShellEscapeParameter(): string
    {
        $shellEscape = '';

        $latexContents = $this->latexFile->getContents();

        if (str_contains($latexContents, '\begin{minted}')
            OR str_contains($latexContents, '\usepackage{minted}')
            OR str_contains($latexContents, '\inputminted')) {
            $shellEscape = '-shell-escape ';
        }

        return $shellEscape;
    }

    private function setEnvironmentVariables(array $options= []): void
    {
        $bibMode = count($this->latexFile->getBibliography()->getPathsToUsedBibFiles()) > 0
            ? 'bibtex'
            : 'none';

        $options['mode'] = $options['mode'] ?? self::MODE_FULL;
        $options['bibMode'] = $options['bibMode'] ?? $bibMode;

        putenv('MODE='.$options['mode']);
        putenv('BIB_MODE='.$options['bibMode']);
        putenv('LATEX_OPTIONS='.$this->getShellEscapeParameter($this->latexFile));

        $wwwDataPath = NULL;
        $wwwDataHome = NULL;

        if (function_exists('config')) {
            $replacement = str_replace('%__useTexLiveVersion{', '\useTexLiveVersion{', $this->latexFile->getContents());
            $this->latexFile->setContents($replacement);
            $selectedVersion = $this->latexFile->getMacro('useTexLiveVersion')?->getArgument();

            $versionPath = config('latex.paths.www-data-path-versions');
            $oldVersions = config('latex.old-versions');
            $supportedVersions = !empty($oldVersions)
                ? explode(';', $oldVersions)
                : [];

            $wwwDataPath = ($versionPath !== NULL AND in_array($selectedVersion, $supportedVersions))
                ? str_replace('{version}', $selectedVersion, $versionPath)
                : config('latex.paths.www-data-path');

            $wwwDataHome = config('latex.paths.www-data-home');
        }

        if (!empty($wwwDataPath)) {
            $wwwDataPath .= '/';
        }

        putenv('PATH='.$wwwDataPath);

        if ($wwwDataHome !== NULL) {
            putenv('HOME='. $wwwDataHome);
        }
    }

    private function getExitCodes(string $logLine): array
    {
        preg_match('/Last LaTeX exit code \[([0-9]*)], Last BibTeX exit code \[([0-9]*)]/', $logLine, $matches);

        $exitCodes = [];
        for ($i= 1; $i<= 2; $i++) {
            $match = $matches[$i] ?? NULL;
            if ($match !== NULL) {
                $match = (int)$match;
            }
            $exitCodes[] = $match;
        }

        return $exitCodes;
    }


    public function compile(array $options = []): void
    {
        $this->setEnvironmentVariables($options);

        $absolutePath = Filesystem::storagePath($this->latexFile->getPath());
        $command = $this->getProfileCommand(). ' '. $absolutePath;

        exec($command, $out);

        $this->profileOutput = $out;
        $lastLine = $out[count($out)-1];
        list($this->latexExitCode, $this->bibtexExitCode) = $this->getExitCodes($lastLine);
    }

    public function getLatexExitCode(): ?int
    {
        return $this->latexExitCode;
    }

    public function getBibtexExitCode(): ?int
    {
        return $this->bibtexExitCode;
    }

    public function getProfileOutput(): array
    {
        return $this->profileOutput;
    }
}