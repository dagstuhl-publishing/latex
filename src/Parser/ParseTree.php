<?php

namespace Dagstuhl\Latex\Parser;

use Dagstuhl\Latex\Parser\TreeNodes\CommentNode;
use Dagstuhl\Latex\Parser\TreeNodes\RootNode;
use Dagstuhl\Latex\Parser\TreeNodes\TextNode;

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
    
    public function insertText(string $text, int $charIndex): TextNode
    {
        if ($charIndex < 0) {
            $charIndex += strlen($text);
        }
        
        $charIndex = max(0, min(strlen($text), $charIndex));

        $path = $this->getPathToChar($charIndex);

        $leaf = end($path);
        $parent = $leaf->parent;
        $childIndex = $parent->indexOf($leaf);

        return $this->insertTextNode($text, $parent, $childIndex);
    }

    /**
     * @param string $content
     * @param int $charIndex
     * @return array an array of root nodes of subtrees (which could consist of just a single node) inserted into this
     * tree or of the nodes that were modified.
     * @throws ParseException
     */
    public function insertContent(string $content, int $charIndex): array
    {
        $path = $this->getPathToChar($charIndex);
        $leaf = end($path);
        $parent = $leaf->parent;
        $leafIndex = $parent->indexOf($leaf);
        $leafString = $leaf->toLatex();

        $prefix = substr($leafString, 0, $charIndex);
        $postfix = substr($leafString, $charIndex);

        $leftSibling = null;
        $rightSibling = null;

        if ($charIndex === 0) {
            $rightSibling = $leaf;
            $childIndex = $leafIndex;
        } elseif ($charIndex === strlen($leafString) - 1) {
            $leftSibling = $leaf;
            $childIndex = $leafIndex + 1;
        } elseif ($leaf instanceof CommentNode) {
            $leaf->raw = $prefix . $content . $postfix;
            $leaf->_invalidateTextCache();
            return [$leaf];
//        } else {
//            $replacement = [
//                new TextNode(-1, $prefix),
//                new TextNode(-1, $postfix)
//            ];
//
//            // TODO:
//
//            $parent->replaceChild($leafIndex, $replacement);
//            $childIndex = $leafIndex + 1;
        }

        $parser = new LatexParser();
        $nodes = $parser->parse($content)->root->getChildren();

        $firstNewNode = $nodes[0];
        $lastNewNode = end($nodes);

        if ($leftSibling === null) {
            if ($rightSibling === null) {

            } else {

            }
        } elseif ($rightSibling === null) {

        } else {
            // check possible merge of $firstNode with $leftSibling

            // check possible merge of $lastNode with $rightSibling
        }

        $parent->addChildren($nodes, $childIndex);

        return $nodes;
    }

    /**
     * @param int $startIndex
     * @param int $length
     * @return array[]
     * @throws \Exception
     */
    public function deleteContent(int $startIndex, int $length): array
    {
        $deletedNodes = [];
        $modifiedNodes = [];

        $endIndex = $startIndex + $length - 1;
        $startPath = $this->getPathToChar($startIndex);
        $endPath = $this->getPathToChar($endIndex);

        $divergeIndex = 1;
        for ($minPathLength = min(count($startPath), count($endPath)); $divergeIndex < $minPathLength; $divergeIndex++) {
            if ($startPath[$divergeIndex] !== $endPath[$divergeIndex]) {
                break;
            }
        }

        $startLeaf = end($startPath);
        $endLeaf = end($endPath);

        $startString = $startLeaf->toLatex();
        if ($startIndex === 0 && ($startLeaf !== $endLeaf || $endIndex + 1 === strlen($startString))) {
            $dns = $this->deleteNode($startLeaf);
            array_push($deletedNodes, ...$dns);
            $deletedNodes[] = $startLeaf;
        } else {
            $startString = substr($startString, $endIndex + 1);
            if ($startLeaf instanceof TextNode) {
                $startLeaf->content = $startString;
            } elseif ($startLeaf instanceof CommentNode) {
                $startLeaf->raw = $startString;
            } else {
                throw new \Exception('Unexpected type of leaf node, must be TextNode or CommentNode: ' . get_class($startLeaf));
            }
            $startLeaf->_invalidateTextCache();
            $modifiedNodes[] = $startLeaf;
        }

        if ($startLeaf !== $endLeaf) {
            if ($endLeaf instanceof CommentNode) {
                $endLeaf->parent->removeChild();
            }

            $endString = substr($endLeaf->toLatex(), 0, $endIndex + 1);
            if ($endLeaf instanceof TextNode) {
                $endLeaf->content = $endString;
            } elseif ($endLeaf instanceof CommentNode) {
                $endLeaf->raw = $endString;
            } else {
                throw new \Exception('Unexpected type of leaf node, must be TextNode or CommentNode: ' . get_class($endLeaf));
            }
            $endLeaf->_invalidateTextCache();
            $modifiedNodes[] = $endLeaf;
        }

        foreach ([&$startPath, &$endPath] as &$path) {
            for ($i = $divergeIndex, $n = count($path) - 1; $i < $n; $i++) {
                $node = $path[$i];
                if ($node->parent === null) break;

                $child = $path[$i + 1];
                $childIndex = $node->parent->indexOf($child);
                if ($childIndex < 0) break;

                for ($j = $node->getChildCount() - 1; $j > $childIndex; $j++) {
                    $dns = $this->deleteNode($node->getChild($j));
                    array_push($deletedNodes, ...$dns);
                }
            }
        }

        return [$deletedNodes, $modifiedNodes];
    }

    public function insertTextNode(string $text, ParseTreeNode $parent, int $childIndex): TextNode
    {
        $childIndex = max(0, min($parent->getChildCount(), $childIndex));
        $textNode = new TextNode($parent->lineNumber, $text);
        $parent->addChild($textNode, $childIndex);
        return $textNode;
    }

    /**
     * @param ParseTreeNode $node 
     * @param bool $deleteChildlessAncestors This should almost always be 'true' unless you know what you're doing.
     * @return array The nodes that were removed from the tree.
     */
    public function deleteNode(ParseTreeNode $node, bool $deleteChildlessAncestors = true): array
    {
        $deletedNodes = [];

        $parent = $node->parent;
        if ($parent !== null) {
            $parent->removeChild($parent->indexOf($node));
            $deletedNodes[] = $node;

            if ($deleteChildlessAncestors) {
                while ($parent->getChildCount() == 0) {
                    $grandParent = $parent->parent;
                    if ($grandParent !== null) {
                        $grandParent->removeChild($grandParent->indexOf($parent));
                        $deletedNodes[] = $parent;
                        $parent = $grandParent;
                    } else {
                        break;
                    }
                }
            }
        }

        if ($this->root->getChildCount() === 0) {
            $this->insertTextNode('', $this->root, 0);
        }

        return $deletedNodes;
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