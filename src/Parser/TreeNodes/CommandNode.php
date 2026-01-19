<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

class CommandNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, string $name)
    {
        parent::__construct($lineNumber);
        $this->addChild(new TextNode($lineNumber, $name));
    }

    public function getName(): string
    {
        return $this->getChild(0)->getText();
    }
}
