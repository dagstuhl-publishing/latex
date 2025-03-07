<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal;

use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Compiler\BuildProfiles\Utilities\GetRequestedLatexVersion;
use Dagstuhl\Latex\Compiler\BuildProfiles\Utilities\ParseExitCodes;
use Dagstuhl\Latex\Utilities\Environment;
use Dagstuhl\Latex\Utilities\Filesystem;

class PdfLatexBibtexLocalProfile extends BasicProfile implements BuildProfileInterface
{
    use ParseExitCodes;
    use GetRequestedLatexVersion;

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

    public function getEnvironmentVariables(array $options = [], $asArray = false): array|string
    {
        $bibMode = count($this->latexFile->getBibliography()->getPathsToUsedBibFiles()) > 0
            ? 'bibtex'
            : 'none';

        $env = [
            'MODE' => $options['mode'] ?? $this->globalOptions['mode'] ?? self::MODE_FULL,
            'BIB_MODE' => $options['bibMode'] ?? $this->globalOptions['bibMode'] ?? $bibMode,
            'LATEX_OPTIONS' => $this->getShellEscapeParameter()
        ];

        $latexUserBinPath = NULL;
        $latexUserHome = NULL;

        if (function_exists('config')) {
            $selectedVersion = static::getRequestedLatexVersion($this->latexFile);
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

        return $asArray
            ? $env
            : Environment::toString($env);
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