<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

class CommentNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, public string $raw)
    {
        parent::__construct($lineNumber);
    }

    public function toLatex(): string
    {
        return $this->raw;
    }

    public function getText(bool $trim = false): string
    {
        return $this->raw;
    }

    public function __toString(): string
    {
        $clean = trim(str_replace(["\n", "\r"], ["", ""], $this->raw));
        return parent::__toString() . ": " . $clean;
    }
}

