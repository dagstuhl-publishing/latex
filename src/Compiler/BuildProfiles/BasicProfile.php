<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles;

use Dagstuhl\Latex\LatexStructures\LatexFile;

class BasicProfile
{
    protected LatexFile $latexFile;
    protected ?int $latexExitCode;
    protected ?int $bibtexExitCode;
    protected array $profileOutput = [];

    public function __construct(LatexFile $latexFile = NULL)
    {
        if ($latexFile !== NULL) {
            $this->latexFile = $latexFile;
        }
    }

    public function setLatexFile(LatexFile $latexFile): void
    {
        $this->latexFile = $latexFile;
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