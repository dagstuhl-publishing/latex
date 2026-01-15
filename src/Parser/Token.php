<?php

namespace Dagstuhl\Latex\Parser;

class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly int       $start, // inclusive
        public readonly int       $end,   // exclusive
        public readonly int       $lineNumber
    )
    {}

    public function __toString(): string
    {
        return "[" . $this->type->name . " start: $this->start, end: $this->end, lineNumber: $this->lineNumber]";
    }
}