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

    public function getArgumentCount(?bool $isOptional = null) {
        $ret = 0;
        foreach ($this->getChildren() as $child) {
            if ($child instanceof ArgumentNode) {
                if (($child->isOptional && $isOptional !== false) ||
                    (!$child->isOptional && $isOptional !== true)) {
                    $ret++;
                }
            }
        }
        return $ret;
    }

    public function __toString(): string
    {
        $clean = str_replace(["\n", "\r"], ["\\n", "\\r"], $this->getChild(0)?->getText());
        $display = (strlen($clean) > 40) ? substr($clean, 0, 37) . "..." : $clean;
        return parent::__toString() . ": \"$display\"";
    }
}
