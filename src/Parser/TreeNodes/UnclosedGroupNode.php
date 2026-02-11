<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

class UnclosedGroupNode extends GroupNode
{
    public function __construct(int $lineNumber)
    {
        parent::__construct($lineNumber);

        // unlike GroupNodes, this class does not have a closing text node
        $this->removeChild(1);
    }

    protected function getNormalizedIndex(int|ParseTreeNode|null $indexOrChild): int
    {
        $childCount = $this->getChildCount();

        if ($indexOrChild === null) {
            return $childCount;
        } elseif ($indexOrChild instanceof ParseTreeNode) {
            $index = $this->indexOf($indexOrChild);
        } else {
            $index = $indexOrChild;
        }

        if ($index < 0) {
            $index += $childCount;
        }

        return max(1, min($index, $childCount));
    }
}