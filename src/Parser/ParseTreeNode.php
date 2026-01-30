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
     * @var ?string the textual content of this node
     */
    protected ?string $text;

    /**
     * @var ?string the full LaTeX string from which this node was constructed during parsing
     */
    protected ?string $latex;

    /**
     * @param int $lineNumber The line number in the original document, will not update if the tree gets modified
     * after parsing.
     */
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

        $this->_invalidateTextCache();
    }

    public function addChildren(array $nodes, ?int $index = null): void
    {
        foreach ($nodes as $node) {
            $node->parent = $this;
        }

        array_splice($this->children, $this->getNormalizedIndex($index), 0, $nodes);

        $this->_invalidateTextCache();
    }

    public function removeChild(int|ParseTreeNode $indexOrNode): ?ParseTreeNode
    {
        $index = $this->getNormalizedIndex($indexOrNode);

        $removed = array_splice($this->children, $index, 1);
        $removed[0]->parent = null;

        $this->_invalidateTextCache();

        return $removed[0];
    }

    protected function getNormalizedIndex(int|ParseTreeNode|null $indexOrNode): int
    {
        $childCount = count($this->children);

        if ($indexOrNode === null) {
            return $childCount;
        } elseif ($indexOrNode instanceof ParseTreeNode) {
            $index = $this->indexOf($indexOrNode);
        } else {
            $index = $indexOrNode;
        }

        if ($index < 0) {
            $index += $childCount;
        }

        $index = max($index, 0);
        $index = min($index, $childCount);

        return $index;
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

    public function _invalidateTextCache(): void
    {
        $ancestor = $this;
        do {
            $ancestor->latex = null;
            $ancestor->text = null;
            $ancestor = $ancestor->parent;
        } while ($ancestor !== null);
    }

    public function __toString(): string
    {
        $parts = explode('\\', get_class($this));
        return end($parts);
    }
}