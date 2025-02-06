<?php

namespace Dagstuhl\Latex\Compiler\LogParser;

use Dagstuhl\Latex\LatexStructures\LatexFile;

interface LogParserInterface
{
    public function __construct(LatexFile $latexFile);

    public function getLatexLog(?string $logFilter): array;

    public function getBibtexLog(?string $logFilter): array;

    public function getMessages(string $messageType): array;
}