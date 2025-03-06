<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal;

use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Utilities\Filesystem;
use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;

class PdfLatexBibtexLocalProfile extends BasicProfile implements BuildProfileInterface
{
    use ParseExitCodes;

    const MODE_FULL = 'full';
    const MODE_LATEX_ONLY = 'latex-only';
    const MODE_BIBTEX_ONLY = 'bibtex-only';

    private function getProfileCommand(): string
    {
        return __DIR__.'/pdflatex-bibtex-local.sh';
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

    public function getEnvironmentVariables(array $options= []): string
    {
        $bibMode = count($this->latexFile->getBibliography()->getPathsToUsedBibFiles()) > 0
            ? 'bibtex'
            : 'none';

        $env = [
            'MODE' => $options['MODE'] ?? $options['mode'] ?? self::MODE_FULL,
            'BIB_MODE' => $options['BIB_MODE'] ?? $options['bibMode'] ?? $bibMode,
            'LATEX_OPTIONS' => $this->getShellEscapeParameter()
        ];

        $latexUserBinPath = NULL;
        $latexUserHome = NULL;

        if (function_exists('config')) {
            $replacement = str_replace('%__useTexLiveVersion{', '\useTexLiveVersion{', $this->latexFile->getContents());
            $this->latexFile->setContents($replacement);
            $selectedVersion = $this->latexFile->getMacro('useTexLiveVersion')?->getArgument();
            $versionPath = config('latex.paths.bin-versions');

            $oldVersions = config('latex.old-versions');
            $supportedVersions = !empty($oldVersions)
                ? explode(';', $oldVersions)
                : [];

            $latexUserBinPath = ($versionPath !== NULL AND in_array($selectedVersion, $supportedVersions))
                ? str_replace('{version}', $selectedVersion, $versionPath)
                : config('latex.paths.bin');

            $latexUserHome = config('latex.paths.home');
        }

        if (!empty($latexUserBinPath)) {
            $env['PATH'] = $latexUserBinPath;
        }

        if (!empty($latexUserHome)) {
            $env['HOME'] = $latexUserHome;
        }

        $envString = '';
        foreach($env as $key=>$value) {
            $envString .= ' ' . $key . '=' . escapeshellarg($value);
        }

        return trim($envString);
    }


    public function getLatexVersion(): string
    {
        $command = $this->getEnvironmentVariables() . ' ' . $this->getProfileCommand() . ' --version';
        exec($command, $out);

        return $out[0] ?? 'pdflatex';
    }

    public function compile(array $options = []): void
    {
        $absolutePath = Filesystem::storagePath($this->latexFile->getPath());
        $command = $this->getEnvironmentVariables($options) . ' ' . $this->getProfileCommand(). ' '. $absolutePath;

        exec($command, $out);

        $this->profileOutput = $out;

        $lastLine = $out[count($out)-1];
        list($this->latexExitCode, $this->bibtexExitCode) = $this->parseExitCodes($lastLine);
    }
}