<?php

namespace Dagstuhl\Latex\Compiler;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use phpDocumentor\Reflection\Types\Null_;

class LatexCompiler
{
    const TEMP_FILE_EXTENSIONS = [ 'vtc', 'aux', 'log' ];

    const LABELS_CHANGED = 'Label(s) may have changed. Rerun';
    const EXTRA_PAGE = 'Temporary extra page added at the end. Rerun to get it removed.';

    const FATAL_ERROR = 100;

    protected LatexFile $latexFile;

    protected string $texFilename;
    protected string $workingDir;
    protected string $relativeWorkingDir;

    protected array $latexOutput = [];
    protected array $bibtexOutput = [];
    protected ?string $exceptionMessage = NULL;

    protected ?int $latexExitCode = NULL;
    protected ?int $bibtexExitCode = NULL;

    protected static ?string $version = NULL;

    /**
     * LatexCompiler constructor.
     */
    public function __construct(LatexFile $latexFile)
    {
        $texFilePath = $latexFile->getPath();

        $relativeWorkingDir = pathinfo($texFilePath, PATHINFO_DIRNAME).'/';

        $this->latexFile = $latexFile;
        $this->texFilename = preg_replace('/\.tex$/', '', basename($texFilePath));
        $this->workingDir = Filesystem::storagePath($relativeWorkingDir);
        $this->relativeWorkingDir = $relativeWorkingDir;
    }

    public function getLatexVersion(): string
    {
        if (self::$version !== NULL) {
            return self::$version;
        }

        $this->setEnvironmentVariables();
        exec(config('lzi.latex.paths.latex-bin'). ' --version', $msg);

        self::$version = $msg[0] ?? 'pdflatex';

        return self::$version;
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

            $versionPath = config('lzi.latex.paths.www-data-path-versions');
            $oldVersions = config('lzi.latex.old-versions');
            $supportedVersions = !empty($oldVersions)
                    ? explode(';', $oldVersions)
                    : [];

            $wwwDataPath = ($versionPath !== NULL AND in_array($selectedVersion, $supportedVersions))
                ? str_replace('{version}', $selectedVersion, $versionPath)
                : config('lzi.latex.paths.www-data-path');

            $wwwDataHome = config('lzi.latex.paths.www-data-home');
        }

        if ($wwwDataPath !== NULL) {
            putenv('PATH='. $wwwDataPath);
        }

        if ($wwwDataHome !== NULL) {
            putenv('HOME='. $wwwDataHome);
        }
    }

    public function compile(): int
    {
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
            config('lzi.latex.paths.latex-bin').
            ' -interaction=nonstopmode '.
            $this->getShellEscapeParameter().
            $texFilename;

        $bibtexCommand = $changeDirectory. ' && '.config('lzi.latex.paths.bibtex-bin').' '.$texFilename;

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
                    return $this->bibtexExitCode;
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
        catch(\Exception $ex) {
            $this->exceptionMessage = $ex;
            $this->latexExitCode = self::FATAL_ERROR;
        }

        return $this->latexExitCode;
    }

    public function compilationSucceeded(bool $bibTexAndLatex = false): bool
    {
        if ($bibTexAndLatex) {
            return ($this->getLatexExitCode() === 0 AND $this->getBibtexExitCode() === 0 && $this->exceptionMessage === NULL);
        }
        else {
            return ($this->getLatexExitCode() === 0 AND $this->exceptionMessage === NULL);
        }
    }

    public function exceptionOccurred(): bool
    {
        return $this->getExceptionMessage() !== NULL;
    }

    public function getLatexOutput(): array
    {
        return $this->latexOutput;
    }

    public function getBibtexOutput(): array
    {
        return $this->bibtexOutput;
    }

    public function getLatexExitCode(): ?int
    {
        return $this->latexExitCode;
    }

    public function getBibtexExitCode(): ?int
    {
        return $this->bibtexExitCode;
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage ?? '';
    }

    public function labelsChanged(): bool
    {
        $logLines = $this->getLatexLog();

        $log = implode(' ', $logLines);

        return stripos($log, self::LABELS_CHANGED) !== false;
    }

    public function getNumberOfPages(): int
    {
        $log = implode(' ', $this->getLatexLog(LatexLogParser::LOG_FILTER_FULL));

        if (preg_match_all('/output written on .* \(([0-9]+) page(s{0,1})/i', $log, $matches) > 0) {
            return (int)$matches[1][0];
        }

        return 0;
    }

    /**
     * @return string[]
     */
    public function getLatexLog(string $logFilter = LatexLogParser::LOG_FILTER_STANDARD): array
    {
        $logParser = new LatexLogParser($this->latexFile, $logFilter);

        return $logParser->getLatexLog();
    }

    /**
     * @return string[]
     */
    public function getBibTexLog(string $logFilter = LatexLogParser::LOG_FILTER_STANDARD): array
    {
        $logParser = new LatexLogParser($this->latexFile, $logFilter);

        return $logParser->getBibtexLog();
    }

    /**
     * Error/warning messages shown below the log-window in Dagstuhl Submission System
     * @param string $messageType (LogParser::MESSAGE_TYPE_ERROR or LogParser::MESSAGE_TYPE_WARNING)
     * @return string[]
     */
    public function getMessages(string $messageType): array
    {
        $logParser = new LatexLogParser($this->latexFile);

        return $logParser->getMessages($messageType);
    }

}