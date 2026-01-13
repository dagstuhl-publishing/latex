<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

class GroupNode extends EnvelopeNode
{
    public function __construct(int $lineNumber)
    {
        parent::__construct(
            $lineNumber,
            new TextNode($lineNumber, '{'),
            new TextNode($lineNumber, '}')
        );
    }
}

