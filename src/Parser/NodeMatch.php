<?php

namespace Dagstuhl\Latex\Parser;

class NodeMatch
{
    public function __construct(public array     $nodes,
                                public int       $firstNodeSubstringOffset,
                                public int       $lastNodeSubstringLength,
                                public string    $string,
                                public ParseTree $parseTree)
    {
    }

    public function getParentMatch(): NodeMatch
    {
        $firstPath = $this->parseTree->getPathToNode($this->nodes[0]);
        $lastPath = $this->parseTree->getPathToNode($this->nodes[count($this->nodes) - 1]);

        $matchRootIndex = 1;
        for ($n = min(count($firstPath), count($lastPath)); $matchRootIndex < $n; $matchRootIndex++) {
            if ($firstPath[$matchRootIndex] !== $lastPath[$matchRootIndex]) {
                break;
            }
        }
        $matchRootIndex--;

        $firstNodeSubstringOffset = $this->getOffsetForPath($firstPath, $matchRootIndex) + $this->firstNodeSubstringOffset;
        $lastNodeSubstringLength = $this->getOffsetForPath($lastPath, $matchRootIndex) + $this->lastNodeSubstringLength;

        return new NodeMatch([$firstPath[0]], $firstNodeSubstringOffset, $lastNodeSubstringLength, $this->string, $this->parseTree);
    }

    /**
     * @param array $path
     * @param int $startIndex
     * @return int the distance in characters between the start of the left-most leaf of
     *             the first element in $path and the start of the last element in $path.
     *             If $startIndex is given, we measure from the left-most child of $path[$startIndex]
     *             instead of the first element in $path.
     */
    private function getOffsetForPath(array $path, int $startIndex = 0): int
    {
        $offset = 0;

        for ($i = $startIndex; $i < count($path) - 1; $i++) {
            $nextPathElement = $path[$i + 1];

            foreach ($path[$i]->getChildren() as $child) {
                if ($child !== $nextPathElement) {
                    $offset += strlen($child->toLatex());
                } else {
                    break;
                }
            }
        }

        return $offset;
    }

    public function __toString(): string
    {
        $string = '';
        foreach ($this->nodes as $node) {
            $string .= $node->toLatex();
        }
        return "[NodeMatch: \"$this->string\" [" . join(", ", $this->nodes) . "] -> \"$string\"]";
    }
}
