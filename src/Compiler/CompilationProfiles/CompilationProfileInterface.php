<?php

namespace Dagstuhl\Latex\Compiler\CompilationProfiles;

use Dagstuhl\Latex\LatexStructures\LatexFile;

interface CompilationProfileInterface
{
    public function compile(LatexFile $latexFile): void;

    public function getLatexVersion(): string;

    public function getLatexExitCode(): ?int;

    public function getBibtexExitCode(): ?int;

    public function getProfileOutput(): array;
}