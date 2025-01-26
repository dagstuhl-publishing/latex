<?php

namespace Dagstuhl\Latex\Compiler\CompilationProfiles;

use Dagstuhl\Latex\LatexStructures\LatexFile;

interface CompilationProfileInterface
{
    public function __construct(LatexFile $latexFile);

    public function compile(): void;

    public function getLatexVersion(): string;

    public function getLatexExitCode(): ?int;

    public function getBibtexExitCode(): ?int;

    public function getProfileOutput(): array;
}