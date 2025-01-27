<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles;

use Dagstuhl\Latex\LatexStructures\LatexFile;

interface BuildProfileInterface
{
    public function __construct(LatexFile $latexFile);

    public function setLatexFile(LatexFile $latexFile);

    public function compile(): void;

    public function getLatexVersion(): string;

    public function getLatexExitCode(): ?int;

    public function getBibtexExitCode(): ?int;

    public function getProfileOutput(): array;
}