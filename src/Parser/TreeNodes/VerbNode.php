<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

class VerbNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, public string $content, public string $delimiter)
    {
        parent::__construct($lineNumber);
    }

    public function toLatex(): string
    {
        return "\\verb" . $this->delimiter . $this->content . $this->delimiter;
    }

    public function getText($trim = false): string
    {
        return $trim ? trim($this->content) : $this->content;
    }

    public function __toString(): string
    {
        $clean = str_replace(["\n", "\r"], ["\\n", "\\r"], $this->content);
        $display = (strlen($clean) > 40) ? substr($clean, 0, 37) . "..." : $clean;
        return parent::__toString() . " $this->delimiter$display$this->delimiter";
    }

}

