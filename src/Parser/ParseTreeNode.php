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

    public function spliceChildren(int|ParseTreeNode|null $indexOrBeforeChild, int $removeLength = 0, array|ParseTreeNode|null $childNodes=null): array
    {
        $index = $this->getNormalizedIndex($indexOrBeforeChild);

        if ($childNodes instanceof ParseTreeNode) {
            $childNodes = [$childNodes];
        }

        if ($childNodes !== null) {
            foreach ($childNodes as $child) {
                $child->parent?->removeChild($child);
            }
        }

        $removed = array_splice($this->children, $index, $removeLength, $childNodes);

        foreach ($removed as $child) {
            $child->parent = null;
        }

        if ($childNodes !== null) {
            foreach ($childNodes as $child) {
                $child->parent = $this;
            }
        }

        $this->_invalidateTextCache();

        return $removed;
    }

    public function addChild(?ParseTreeNode $node, int|ParseTreeNode|null $indexOrBeforeChild = null): void
    {
        $this->spliceChildren($indexOrBeforeChild, 0, $node);
    }

    public function addChildren(array $nodes, int|ParseTreeNode|null $indexOrBeforeChild = null): void
    {
        $this->spliceChildren($indexOrBeforeChild, 0, $nodes);
    }

    public function removeChild(int|ParseTreeNode $indexOrChild): ParseTreeNode
    {
        $removed = $this->spliceChildren($indexOrChild, 1);

        return $removed[0];
    }

    public function removeChildren(int|ParseTreeNode $indexOrChild, int $removeLength): array
    {
        return $this->spliceChildren($indexOrChild, $removeLength);
    }

    protected function getNormalizedIndex(int|ParseTreeNode|null $indexOrChild): int
    {
        $childCount = count($this->children);

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

        return max(0, min($index, $childCount));
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