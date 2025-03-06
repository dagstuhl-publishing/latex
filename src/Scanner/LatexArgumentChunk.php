<?php

namespace Dagstuhl\Latex\Scanner;

class LatexArgumentChunk extends LatexChunk
{
    public string $raw;

    public bool $optional;

    public ?string $braces;

    public string $body;

    public function __construct(int $lineNo, string $raw, bool $optional, ?string $braces, string $body)
    {
        parent::__construct($lineNo, $raw);
        $this->optional = $optional;
        $this->braces = $braces;
        $this->body = $body;
    }

    public function isArgument(): bool
    {
        return true;
    }
}
