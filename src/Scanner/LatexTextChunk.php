<?php

namespace Dagstuhl\Latex\Scanner;

class LatexTextChunk extends LatexChunk
{
    public string $text;

    public function __construct(int $lineNo, string $raw, string $text)
    {
        parent::__construct($lineNo, $raw);
        $this->text = $text;
    }

    public function isText(): bool
    {
        return true;
    }
}
