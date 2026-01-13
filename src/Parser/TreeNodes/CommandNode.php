<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

class CommandNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, public readonly string $name)
    {
        parent::__construct($lineNumber);
    }

    public function toLatex(): string
    {
        return $this->name . parent::toLatex();
    }

    public function __toString(): string
    {
        return parent::__toString() . ": $this->name";
    }
}
