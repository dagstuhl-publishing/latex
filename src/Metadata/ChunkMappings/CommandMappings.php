<?php

namespace Dagstuhl\Latex\Metadata\ChunkMappings;

use Dagstuhl\Latex\Scanner\LatexChunk;
use Dagstuhl\Latex\Scanner\LatexScanner;

class CommandMappings
{
    public static function removeCommand(LatexChunk $chunk, LatexScanner $scanner): string
    {
        return '';
    }

    public static function removeCommandWith1Arg(LatexChunk $chunk, LatexScanner $scanner): string
    {
        $scanner->readArgument();
        return '';
    }

    public static function replaceByBody(LatexChunk $chunk, LatexScanner $scanner): string
    {
        $arg = $scanner->readArgument();

        if ($arg === null) {
            return '';
        }

        return $arg->braces === null
            ? $arg->raw
            : $arg->body;
    }

    public static function enquoteFirstArgument(LatexChunk $chunk, LatexScanner $scanner): string
    {
        return '"'.($scanner->readArgument()?->body ?? '').'"';
    }

}