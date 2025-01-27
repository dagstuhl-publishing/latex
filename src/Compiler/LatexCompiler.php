<?php

namespace Dagstuhl\Latex\Compiler;

use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal\PdfLatexBibtexLocalProfile;
use Dagstuhl\Latex\Compiler\LogParser\DefaultLatexLogParser;
use Dagstuhl\Latex\Compiler\LogParser\LogParserInterface;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Exception;

class LatexCompiler
{
    const FATAL_ERROR = 100;

    protected LatexFile $latexFile;
    protected BuildProfileInterface $compilationProfile;
    protected LogParserInterface $logParser;

    protected ?string $exceptionMessage = NULL;
    protected ?int $latexExitCode = NULL;
    protected ?int $bibtexExitCode = NULL;

    protected ?string $latexVersion = NULL;

    public function __construct(
        LatexFile             $latexFile,
        BuildProfileInterface $compilationProfile = NULL,
        LogParserInterface    $logParser = NULL
    )
    {
        $this->latexFile = $latexFile;
        $this->compilationProfile = $compilationProfile ?? new PdfLatexBibtexLocalProfile();
        $this->compilationProfile->setLatexFile($latexFile);
        $this->logParser = $logParser ?? new DefaultLatexLogParser($latexFile);
    }

    public function getLatexVersion(): string
    {
        if ($this->latexVersion === NULL) {
            $this->latexVersion = $this->compilationProfile->getLatexVersion();
        }

        return $this->latexVersion;
    }

    public function compile(array $options = []): int
    {
        try {
            $this->compilationProfile->compile($options);
            $this->latexExitCode = $this->compilationProfile->getLatexExitCode();
            $this->bibtexExitCode = $this->compilationProfile->getBibtexExitCode();

            if ($this->latexExitCode === NULL) {
                $this->latexExitCode = self::FATAL_ERROR;
            }
        }
        catch(Exception $ex) {
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

    public function getProfileOutput(): array
    {
        return $this->compilationProfile->getProfileOutput();
    }

    public function getNumberOfPages(): int
    {
        $log = implode(' ', $this->getLatexLog(DefaultLatexLogParser::LOG_FILTER_FULL));

        if (preg_match_all('/output written on .* \(([0-9]+) page(s{0,1})/i', $log, $matches) > 0) {
            return (int)$matches[1][0];
        }

        return 0;
    }

    /**
     * @return string[]
     */
    public function getLatexLog(string $logFilter = NULL): array
    {
        return $this->logParser->getLatexLog($logFilter);
    }

    /**
     * @return string[]
     */
    public function getBibTexLog(string $logFilter = NULL): array
    {
        return $this->logParser->getBibtexLog($logFilter);
    }

    /**
     * Error/warning messages shown below the log-window in Dagstuhl Submission System
     * @param string $messageType (LogParser::MESSAGE_TYPE_ERROR or LogParser::MESSAGE_TYPE_WARNING)
     * @return string[]
     */
    public function getMessages(string $messageType): array
    {
        return $this->logParser->getMessages($messageType);
    }

}