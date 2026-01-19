<?php

namespace Dagstuhl\Latex\Parser;

/**
 * Base class for all LaTeX Parse Tree nodes.
 */
abstract class ParseTreeNode
{
    /** @var ParseTreeNode[] */
    private array $children = [];

    /** Reference to the parent node for easier tree traversal */
    public ?ParseTreeNode $parent = null;

    /**
     * @var the textual content of this node
     */
    protected ?string $text;

    /**
     * @var the full LaTeX string from which this node was constructed during parsing
     */
    protected ?string $latex;

    public function __construct(public int $lineNumber)
    {
        $this->text = null;
        $this->latex = null;
    }

    public function addChild(?ParseTreeNode $node, ?int $index = null): void
    {
        if ($node !== null) {
            $node->parent = $this;
        }

        array_splice($this->children, $this->getNormalizedIndex($index), 0, [$node]);

        $ancestor = $this;
        while ($ancestor !== null) {
            $ancestor->text = null;
            $ancestor->latex = null;
            $ancestor = $ancestor->parent;
        }
    }

    public function addChildren(array $nodes, ?int $index = null): void
    {
        foreach ($nodes as $node) {
            $node->parent = $this;
        }

        array_splice($this->children, $this->getNormalizedIndex($index), 0, $nodes);

        $ancestor = $this;
        while ($ancestor !== null) {
            $ancestor->text = null;
            $ancestor->latex = null;
            $ancestor = $ancestor->parent;
        }
    }

    public function removeChild(int $index): ?ParseTreeNode
    {
        $index = $this->getNormalizedIndex($index);

        $removed = array_splice($this->children, $index, 1);
        $removed[0]->parent = null;

        $ancestor = $this;
        while ($ancestor !== null) {
            $ancestor->text = null;
            $ancestor->latex = null;
            $ancestor = $ancestor->parent;
        }

        return $removed[0];
    }

    protected function getNormalizedIndex(?int $index): int
    {
        $childCount = count($this->children);

        if ($index === null) {
            $index = $childCount;
        } else {
            if ($index < 0) {
                $index += $childCount;
            }

            $index = max($index, 0);
            $index = min($index, $childCount);
        }

        return $index;
    }

    public function removeFromParent(): void
    {
        if ($this->parent !== null) {
            $index = $this->parent->indexOf($this);
            if ($index > -1) {
                $this->parent->removeChild($index);
            }
        }
    }

    public function getChildCount(): int
    {
        return count($this->children);
    }

    public function getChild(int $index): ?ParseTreeNode
    {
        return $this->children[$this->getNormalizedIndex($index)] ?? null;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function indexOf(ParseTreeNode $node): int
    {
        $index = array_search($node, $this->children, true);
        return ($index === false) ? -1 : $index;
    }

    public function getText(bool $trim = false): string
    {
        if ($this->text === null) {
            $this->text = "";
            foreach ($this->getChildren() as $child) {
                $this->text .= $child->getText();
            }
        }
        return $trim ? trim($this->text) : $this->text;
    }

    public function toLatex(): string
    {
        if ($this->latex === null) {
            $this->latex = "";
            foreach ($this->children as $child) {
                $this->latex .= $child->toLatex();
            }
        }
        return $this->latex;
    }

    public function toTreeString(string $indent = ''): string
    {
        $str = $indent . $this;
        foreach ($this->children as $child) {
            $str .= "\n" . $child->toTreeString($indent . "  ");
        }
        return $str;
    }

    public function __toString(): string
    {
        $parts = explode('\\', get_class($this));
        return end($parts);
    }
}