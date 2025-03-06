<?php

namespace Dagstuhl\Latex\Scanner;

class LatexVerbChunk extends LatexChunk
{
    public string $text;

    public function __construct(int $lineNo, string $raw, string $text)
    {
        parent::__construct($lineNo, $raw);
        $this->text = $text;
    }

    public function isVerb(): bool
    {
        return true;
    }
}