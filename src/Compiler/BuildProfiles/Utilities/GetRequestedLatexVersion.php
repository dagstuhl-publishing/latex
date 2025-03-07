<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\Utilities;

use Dagstuhl\Latex\LatexStructures\LatexFile;

trait GetRequestedLatexVersion
{
    public static function getRequestedLatexVersion(?LatexFile $latexFile): ?string
    {
        preg_match('/%__useTexLiveVersion\{([0-9]+)}/', $latexFile?->getContents() ?? '', $matches);
        return $matches[1] ?? NULL;
    }
}