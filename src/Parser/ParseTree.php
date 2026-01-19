<?php

namespace Dagstuhl\Latex\Parser;

use Dagstuhl\Latex\Parser\TreeNodes\RootNode;

class ParseTree
{
    public function __construct(public RootNode $root)
    {
    }

    /**
     * @param ParseTreeNode $node
     * @return array<ParseTreeNode>
     */
    public function getPathToNode(ParseTreeNode $node, bool $reversed = false): array
    {
        $path = [];
        while ($node !== null) {
            $path[] = $node;
            $node = $node->parent;
        }

        if ($reversed) {
            return $path;
        } else {
            return array_reverse($path);
        }
    }

    public function getPathToChar(int &$charOffset, bool $reversed = false): array
    {
        $node = $this->root;

        $path = [$node];

        while ($node->getChildCount() > 0) {
            foreach ($node->getChildren() as $child) {
                $childLength = strlen($child->toLatex());

                if ($charOffset < $childLength) {
                    $node = $child;
                    $path[] = $node;
                    break;
                } else {
                    $charOffset -= $childLength;
                }
            }
        }

        if ($reversed) {
            return array_reverse($path);
        } else {
            return $path;
        }
    }

    public function preg_match(
        string $pattern,
        array  &$matches = null,
        int    $flags = 0,
        int    $offset = 0
    ): int|false
    {
        if ($matches === null) {
            $matches = [];
        }

        $sourceMatches = [];
        $ret = preg_match($pattern, $this->root->toLatex(), $sourceMatches, $flags | PREG_OFFSET_CAPTURE, $offset);

        if ($ret) {
            foreach ($sourceMatches as $matchIndex => $sourceMatch) {
                $matches[$matchIndex] = $this->createNodeMatch(...$sourceMatch);
            }
        }

        return $ret;
    }

    public function preg_match_all(
        string $pattern,
        array  &$matches = null,
        int    $flags = 0,
        int    $offset = 0
    ): int|false
    {
        if ($matches === null) {
            $matches = [];
        }

        $sourceMatches = [];
        $ret = preg_match_all($pattern, $this->root->toLatex(), $sourceMatches, $flags | PREG_OFFSET_CAPTURE, $offset);

        if ($ret) {
            foreach ($sourceMatches as $outerIndex => $outerMatch) {
                foreach ($outerMatch as $innerIndex => $sourceMatch) {
                    $matches[$outerIndex][$innerIndex] = $this->createNodeMatch(...$sourceMatch);
                }
            }
        }

        return $ret;
    }

    private function createNodeMatch($matchString, $matchOffset): NodeMatch
    {
        $length = strlen($matchString);

        $firstLeafOffset = $matchOffset;
        $lastLeafOffset = $matchOffset + $length - 1;

        $firstPath = $this->getPathToChar($firstLeafOffset);
        $lastPath = $this->getPathToChar($lastLeafOffset);

        $divergeIndex = 1;
        $minPathLength = min(count($firstPath), count($lastPath));
        for (; $divergeIndex < $minPathLength; $divergeIndex++) {
            if ($firstPath[$divergeIndex] !== $lastPath[$divergeIndex]) {
                break;
            }
        }

        $lastCommon = $firstPath[$divergeIndex - 1];
        $firstIndex = $divergeIndex < count($firstPath) ? $lastCommon->indexOf($firstPath[$divergeIndex]) : -1;
        $lastIndex = $divergeIndex < count($lastPath) ? $lastCommon->indexOf($lastPath[$divergeIndex]) : -1;

        if ($divergeIndex == $minPathLength) {
            $nodes = [$lastCommon];
        } else {

            $lastCommonChildren = $lastCommon->getChildren();
            $nodes = array_slice($lastCommonChildren, $firstIndex, $lastIndex - $firstIndex + 1);

            for ($i = $divergeIndex + 1; $i < count($firstPath); $i++) {
                $node = $firstPath[$i];
                $parent = $node->parent;
                $nodeIndex = $parent->indexOf($node);
                if ($nodeIndex > 0) {
                    array_splice($nodes, 0, 1, array_slice($parent->getChildren(), $nodeIndex));
                }
            }

            for ($i = $divergeIndex + 1; $i < count($lastPath); $i++) {
                $node = $lastPath[$i];
                $parent = $node->parent;
                $nodeIndex = $parent->indexOf($node);
                if ($nodeIndex < $parent->getChildCount() - 1) {
                    array_splice($nodes, -1, 1, array_slice($parent->getChildren(), 0, $nodeIndex + 1));
                }
            }

            if ($nodes[0] === $lastCommonChildren[0] &&
                $nodes[count($nodes) - 1] === $lastCommonChildren[count($lastCommonChildren) - 1]) {
                $nodes = [$lastCommon];
            }
        }

        return new NodeMatch($nodes, $firstLeafOffset, $lastLeafOffset, $matchString, $this);
    }

    public function toLatex(): string
    {
        return $this->root->toLatex();
    }

    public function __toString(): string
    {
        return $this->root->toTreeString();
    }
}