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

    private function setEnvironmentVariables(array $options= []): void
    {
        $bibMode = count($this->latexFile->getBibliography()->getPathsToUsedBibFiles()) > 0
            ? 'bibtex'
            : 'none';

        $options['mode'] = $options['mode'] ?? self::MODE_FULL;
        $options['bibMode'] = $options['bibMode'] ?? $bibMode;

        putenv('MODE='.$options['mode']);
        putenv('BIB_MODE='.$options['bibMode']);
        putenv('LATEX_OPTIONS='.$this->getShellEscapeParameter());

        $latexUserBinPath = NULL;
        $latexUserHome = NULL;

        if (function_exists('config')) {
            $replacement = str_replace('%__useTexLiveVersion{', '\useTexLiveVersion{', $this->latexFile->getContents());
            $this->latexFile->setContents($replacement);
            $selectedVersion = $this->latexFile->getMacro('useTexLiveVersion')?->getArgument();

            $versionPath = config('latex.paths.bin-versions')
                ?? config('latex.paths.www-data-path-versions'); // deprecated -> remove

            $oldVersions = config('latex.old-versions');
            $supportedVersions = !empty($oldVersions)
                ? explode(';', $oldVersions)
                : [];

            $latexUserBinPath = ($versionPath !== NULL AND in_array($selectedVersion, $supportedVersions))
                ? str_replace('{version}', $selectedVersion, $versionPath)
                : config('latex.paths.bin')
                    ?? config('latex.paths.www-data-path'); // deprecated -> remove

            $latexUserHome = config('latex.paths.home')
                            ?? config('latex.paths.www-data-home'); // deprecated -> remove
        }

        if (!empty($latexUserBinPath)) {
            $latexUserBinPath .= '/';
        }

        putenv('PATH='.$latexUserBinPath);

        if ($latexUserHome !== NULL) {
            putenv('HOME='. $latexUserHome);
        }
    }


    public function getLatexVersion(): string
    {
        $this->setEnvironmentVariables();
        $command = $this->getProfileCommand(). ' --version';
        exec($command, $out);

        return $out[0] ?? 'pdflatex';
    }

    public function compile(array $options = []): void
    {
        $this->setEnvironmentVariables($options);

        $absolutePath = Filesystem::storagePath($this->latexFile->getPath());
        $command = $this->getProfileCommand(). ' '. $absolutePath;

        exec($command, $out);

        $this->profileOutput = $out;

        $lastLine = $out[count($out)-1];
        list($this->latexExitCode, $this->bibtexExitCode) = $this->parseExitCodes($lastLine);
    }

}