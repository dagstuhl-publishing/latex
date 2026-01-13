<?php

namespace Dagstuhl\Latex\Parser;

/**
 * Base class for all LaTeX Parse Tree nodes.
 */
abstract class ParseTreeNode
{
    /** @var ParseTreeNode[] */
    protected array $children = [];

    /** Reference to the parent node for easier tree traversal */
    public ?ParseTreeNode $parent = null;

    public function __construct(public int $lineNumber)
    {
    }

    public function addChild(ParseTreeNode $node): void
    {
        $node->parent = $this;
        $this->children[] = $node;
    }

    public function addChildren(array $nodes): void
    {
        foreach ($nodes as $node) {
            $node->parent = $this;
        }

        // Splice into the end of the children array
        array_splice($this->children, count($this->children), 0, $nodes);
    }

    public function getChildCount(): int
    {
        return count($this->children);
    }

    public function getChild(int $index): ?ParseTreeNode
    {
        if ($index < 0) {
            $index += count($this->children);
        }

        return $this->children[$index] ?? null;
    }

    public function removeChild(int $index): ?ParseTreeNode
    {
        if ($index < 0) {
            $index += count($this->children);
        }

        if (!isset($this->children[$index])) {
            return null;
        }

        $removed = array_splice($this->children, $index, 1);
        return $removed[0];
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function indexOf(ParseTreeNode $node): int
    {
        $idx = array_search($node, $this->children, true);
        return ($idx === false) ? -1 : $idx;
    }

    public function getText(bool $trim = false): string
    {
        $text = "";
        foreach ($this->getChildren() as $child) {
            $text .= $child->getText();
        }
        return $trim ? trim($text) : $text;
    }

    public function toLatex(): string
    {
        $str = '';
        foreach ($this->children as $child) {
            $str .= $child->toLatex();
        }
        return $str;
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