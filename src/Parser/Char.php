<?php

namespace Dagstuhl\Latex\Parser;

/**
 * A private utility for the Lexer to track character-to-source mapping.
 */
class Char
{
    public int $charCode;
    public int $lineNumber;
    public int $charStart;
    public int $charEnd;

    public function __construct(int $charCode, int $lineNumber, int $charStart, int $charEnd)
    {
        $this->charCode   = $charCode;
        $this->lineNumber = $lineNumber;
        $this->charStart  = $charStart;
        $this->charEnd    = $charEnd;
    }
}
