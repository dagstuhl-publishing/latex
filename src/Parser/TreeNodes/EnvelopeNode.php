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
        $opening->parent = $this;
        $closing->parent = $this;
    }

    public function getOpening(): ParseTreeNode
    {
        return parent::getChild(0);
    }

    public function getClosing(): ParseTreeNode
    {
        return parent::getChild(parent::getChildCount() - 1);
    }

    /**
     * @throws ParseException
     */
    public function addChild(?ParseTreeNode $node, ?int $index = null): void
    {
        $index = $this->getNormalizedIndex($index);

        if ($index < 1 || $index > $this->getChildCount() - 1) {
            throw new \Exception("Index out of bounds: " . $index);
        }

        parent::addChild($node, $index);
    }

    /**
     * @throws ParseException
     */
    public function addChildren(array $nodes, ?int $index = null): void
    {
        $index = $this->getNormalizedIndex($index);

        if ($index < 1 || $index > $this->getChildCount() - 1) {
            throw new \Exception("Index out of bounds: " . $index);
        }

        parent::addChildren($nodes, $index);
    }

    public function removeChild(int $index): ?ParseTreeNode
    {
        $index = $this->getNormalizedIndex($index);

        if ($index < 1 || $index > $this->getChildCount() - 1) {
            throw new \Exception("Index out of bounds: " . $index);
        }

        return parent::removeChild($index);
    }

    protected function getNormalizedIndex(?int $index): int
    {
        if ($index === null) {
            if ($this->getChildCount() < 2) {
                return 0; // only happens during construction
            } else {
                return $this->getChildCount() - 1;
            }
        } else {
            return parent::getNormalizedIndex($index);
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
