<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal;

trait ParseExitCodes
{
    private function parseExitCodes(string $logLine): array
    {
        preg_match('/Last LaTeX exit code \[([0-9]*)], Last BibTeX exit code \[([0-9]*)]/', $logLine, $matches);

        $exitCodes = [];
        for ($i= 1; $i<= 2; $i++) {
            $match = $matches[$i] ?? NULL;
            if ($match !== NULL) {
                $match = (int)$match;
            }
            $exitCodes[] = $match;
        }

        return $exitCodes;
    }
}