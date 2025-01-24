<?php

namespace Dagstuhl\Latex\Compiler;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use phpDocumentor\Reflection\Types\Null_;

class LatexCompiler
{
    const FATAL_ERROR = 100;

    protected LatexFile $latexFile;

    protected string $texFilename;
    protected string $workingDir;

    protected array $latexOutput = [];
    protected array $bibtexOutput = [];
    protected ?string $exceptionMessage = NULL;

    protected ?int $latexExitCode = NULL;
    protected ?int $bibtexExitCode = NULL;

    protected static ?string $version = NULL;
    protected string $profile;

    /**
     * LatexCompiler constructor.
     */
    public function __construct(LatexFile $latexFile, string $profile)
    {
        $texFilePath = $latexFile->getPath();

        $relativeWorkingDir = pathinfo($texFilePath, PATHINFO_DIRNAME).'/';

        $this->latexFile = $latexFile;
        $this->texFilename = preg_replace('/\.tex$/', '', basename($texFilePath));
        $this->workingDir = Filesystem::storagePath($relativeWorkingDir);
        $this->profile = $profile;
    }

    public function getLatexVersion(): string
    {
        if (self::$version !== NULL) {
            return self::$version;
        }

        $this->setEnvironmentVariables();
        exec(config('latex.paths.latex-bin'). ' --version', $msg);

        self::$version = $msg[0] ?? 'pdflatex';

        return self::$version;
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
        $options['latexMode'] = $options['latexMode'] ?? 'full';
        $options['bibMode'] = $options['bibMode'] ?? 'bibtex';

        putenv('WORK_DIR='.$this->workingDir);
        putenv('FILE_NAME='.$this->texFilename);
        putenv('LATEX_MODE='.$options['latexMode']);
        putenv('BIB_MODE='.$options['bibMode']);
        putenv('LATEX_OPTIONS='.$this->getShellEscapeParameter());

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
        putenv('LATEX_BIN=pdflatex');
        putenv('BIBTEX_BIN=bibtex');

        if ($wwwDataHome !== NULL) {
            putenv('HOME='. $wwwDataHome);
        }
    }

    public function compile(array $options): int
    {
        $this->setEnvironmentVariables($options);

        $profile = __DIR__.'/CompilationProfiles/'.$this->profile.'.sh';

        try {
            exec($profile, $out);
            $lastLine = $out[count($out)-1];
            preg_match('/Last LaTeX exit code \[([0-9]*)], Last BibTeX exit code \[([0-9]*)]/', $lastLine, $matches);

            $matches[1] = (int)$matches[1];

            if (($matches[2] ?? NULL) === '') {
                $matches[2] = NULL;
            }

            if ($matches[2] !== NULL) {
                $matches[2] = (int)$matches[2];
            }

            $this->latexExitCode = $matches[1] ?? NULL;
            $this->bibtexExitCode = $matches[2] ?? NULL;
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