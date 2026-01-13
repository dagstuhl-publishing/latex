<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;

abstract class EnvelopeNode extends ParseTreeNode
{
    public function __construct(int $lineNumber, ParseTreeNode $opening = null, ParseTreeNode $closing = null)
    {
        parent::__construct($lineNumber);
        $this->children = [null, null];
        $this->setOpening($opening);
        $this->setClosing($closing);
    }

    public function addChild(ParseTreeNode $node): void
    {
        $node->parent = $this;
        array_splice($this->children, -1, 0, [$node]);
    }

    public function addChildren(array $nodes): void
    {
        foreach ($nodes as $node) {
            $node->parent = $this;
        }
        array_splice($this->children, -1, 0, $nodes);
    }

    public function setOpening(ParseTreeNode $opening): void
    {
        $opening->parent = $this;
        $this->children[0] = $opening;
    }

    public function setClosing(ParseTreeNode $closing): void
    {
        $closing->parent = $this;
        $this->children[count($this->children) - 1] = $closing;
    }

    public function getOpening(): ParseTreeNode
    {
        return $this->children[0];
    }

    public function getClosing(): ParseTreeNode
    {
        return $this->children[count($this->children) - 1];
    }

    public function getChildCount(): int
    {
        return max(0, count($this->children) - 2);
    }

    public function getChild(int $index): ?ParseTreeNode
    {
        $count = count($this->children);
        $internalIndex = $index + ($index < 0 ? $count - 1 : 1);

        if ($internalIndex < 1 || $internalIndex > $count - 2) {
            return null;
        }

        return $this->children[$internalIndex] ?? null;
    }

    public function removeChild(int $index): ?ParseTreeNode
    {
        $count = count($this->children);
        $internalIndex = $index + ($index < 0 ? $count - 1 : 1);

        if ($internalIndex < 1 || $internalIndex > $count - 2) {
            return null;
        }

        $removed = array_splice($this->children, $internalIndex, 1);
        return $removed[0];
    }

    public function getChildren(): array
    {
        return array_slice($this->children, 1, -1);
    }

    public function indexOf(ParseTreeNode $node): int
    {
        $idx = array_search($node, $this->children, true);
        if ($idx === false || $idx === 0 || $idx === count($this->children) - 1) {
            return -1;
        }
        return $idx - 1;
    }
}
