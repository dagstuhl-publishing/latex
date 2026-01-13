<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

class TextNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, public string $content)
    {
        parent::__construct($lineNumber);
    }

    public function toLatex(): string
    {
        return $this->content;
    }

    public function getText(bool $trim = false): string
    {
        return $trim ? trim($this->content) : $this->content;
    }

    public function __toString(): string
    {
        $clean = str_replace(["\n", "\r"], ["\\n", "\\r"], $this->content);
        $display = (strlen($clean) > 40) ? substr($clean, 0, 37) . "..." : $clean;
        return parent::__toString() . ": \"$display\"";
    }
}