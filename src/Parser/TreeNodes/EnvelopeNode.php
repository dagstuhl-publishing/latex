<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseException;
use Dagstuhl\Latex\Parser\ParseTreeNode;
use http\Exception\InvalidArgumentException;

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
            throw new \InvalidArgumentException("Index out of bounds: " . $index);
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
            throw new \InvalidArgumentException("Index out of bounds: " . $index);
        }

        parent::addChildren($nodes, $index);
    }

    public function removeChild(int $index): ?ParseTreeNode
    {
        $index = $this->getNormalizedIndex($index);

        if ($index < 1 || $index > $this->getChildCount() - 1) {
            throw new \InvalidArgumentException("Index out of bounds: " . $index);
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

    }
