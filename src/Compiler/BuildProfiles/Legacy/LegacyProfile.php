<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\Legacy;

use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use Exception;

class LegacyProfile extends BasicProfile implements BuildProfileInterface
{
    const TEMP_FILE_EXTENSIONS = [ 'vtc', 'aux', 'log' ];

    const LABELS_CHANGED = 'Label(s) may have changed. Rerun';
    const EXTRA_PAGE = 'Temporary extra page added at the end. Rerun to get it removed.';

    const FATAL_ERROR = 100;

    protected ?string $version = NULL;

    protected string $texFilename;
    protected string $workingDir;
    protected string $relativeWorkingDir;

    protected array $latexOutput = [];
    protected array $bibtexOutput = [];
    protected ?string $exceptionMessage = NULL;

    protected ?int $latexExitCode = NULL;
    protected ?int $bibtexExitCode = NULL;

    public function __construct(LatexFile $latexFile = NULL)
    {
        parent::__construct($latexFile);

        $texFilePath = $latexFile->getPath();

        $relativeWorkingDir = pathinfo($texFilePath, PATHINFO_DIRNAME).'/';

        $this->texFilename = preg_replace('/\.tex$/', '', basename($texFilePath));
        $this->workingDir = Filesystem::storagePath($relativeWorkingDir);
        $this->relativeWorkingDir = $relativeWorkingDir;
    }

    public function getLatexVersion(): string
    {
        if ($this->version !== NULL) {
            return $this->version;
        }

        $this->setEnvironmentVariables();
        exec(config('latex.paths.latex-bin'). ' --version', $msg);

        $this->version = $msg[0] ?? 'pdflatex';

        return $this->version;
    }

    public function clearTempFiles(): void
    {
        $texFilename = $this->relativeWorkingDir.$this->texFilename;

        foreach(self::TEMP_FILE_EXTENSIONS as $ext) {
            $path = $texFilename.'.'.$ext;

            Filesystem::delete($path);
        }
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

    private function setEnvironmentVariables(): void
    {
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

        if ($wwwDataPath !== NULL) {
            putenv('PATH='. $wwwDataPath);
        }

        if ($wwwDataHome !== NULL) {
            putenv('HOME='. $wwwDataHome);
        }
    }

    public function labelsChanged(): bool
    {
        $log = Filesystem::get($this->relativeWorkingDir.$this->texFilename.'.log');
        return stripos($log, self::LABELS_CHANGED) !== false;
    }

    public function compile(): void
    {
        $this->profileOutput[] = 'LaTex build profile: LegacyProfile';

        $this->setEnvironmentVariables();
        $this->clearTempFiles();

        $texFilename = $this->relativeWorkingDir.$this->texFilename;
        $bblFile = $texFilename.'.bbl';
        $logFile = $texFilename.'.log';

        if (Filesystem::exists($bblFile)) {
            Filesystem::delete($bblFile.'.old');
            Filesystem::move($bblFile, $bblFile . '.old');
        }

        $changeDirectory = 'cd '.$this->workingDir;

        // put "..." around filename to handle special chars (like " ", "(", ...)
        $texFilename = '"'.$this->texFilename.'"';

        $latexCommand = $changeDirectory. ' && '.
            config('latex.paths.latex-bin').
            ' -interaction=nonstopmode '.
            $this->getShellEscapeParameter().
            $texFilename;

        $bibtexCommand = $changeDirectory. ' && '.config('latex.paths.bibtex-bin').' '.$texFilename;

        try {

            exec($latexCommand, $this->latexOutput, $this->latexExitCode);

            $output = implode("\n", $this->latexOutput);

            // extra run on "Temporary extra page"-warning
            if ($this->latexExitCode !== 0 AND str_contains($output, self::EXTRA_PAGE)) {
                exec($latexCommand, $this->latexOutput, $this->latexExitCode);
            }

            if ($this->latexExitCode !== 0) {
                exec($latexCommand, $this->latexOutput, $this->latexExitCode);
            }

            $this->bibtexExitCode = 0;

            if (count($this->latexFile->getBibliography()->getPathsToUsedBibFiles()) > 0) {

                exec($bibtexCommand, $this->bibtexOutput, $this->bibtexExitCode);

                if ($this->bibtexExitCode !== 0 AND $this->bibtexExitCode !== 2) {
                    return;
                }

                exec($latexCommand);
            }

            Filesystem::delete($logFile);

            exec($latexCommand, $this->latexOutput, $this->latexExitCode);

            if ($this->labelsChanged()) {
                Filesystem::delete($logFile);

                exec($latexCommand, $this->latexOutput, $this->latexExitCode);
            }

        }
        catch(Exception $ex) {
            $this->exceptionMessage = $ex;
            $this->latexExitCode = self::FATAL_ERROR;
        }
    }
}