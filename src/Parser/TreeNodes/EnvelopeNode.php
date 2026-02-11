<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseException;
use Dagstuhl\Latex\Parser\ParseTreeNode;

abstract class EnvelopeNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, ParseTreeNode $opening = null, ParseTreeNode $closing = null)
    {
        parent::__construct($lineNumber);
        parent::addChildren([$opening, $closing]);
        if ($opening !== null) $opening->parent = $this;
        if ($closing !== null) $closing->parent = $this;
    }

    public function getOpening(): ParseTreeNode
    {
        return parent::getChild(0);
    }

    public function getClosing(): ParseTreeNode
    {
        return parent::getChild(parent::getChildCount() - 1);
    }

    protected function getNormalizedIndex(int|ParseTreeNode|null $indexOrChild): int
    {
        $childCount = $this->getChildCount();
        if ($indexOrChild === null && $childCount >= 2) {
            return $childCount - 1;
        } else {
            return parent::getNormalizedIndex($indexOrChild);
        }
    }

    public function getText(bool $trim = false): string
    {
        if ($this->text === null) {
            $this->text = "";
            $children = $this->getChildren();
            for ($i = 1, $n = count($children) - 1; $i < $n; $i++) {
                $this->text .= $children[$i]->getText();
            }
        }
        return $trim ? trim($this->text) : $this->text;
    }
}
