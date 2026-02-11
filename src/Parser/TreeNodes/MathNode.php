<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

class MathNode extends EnvelopeNode
{
    public function __construct(int $lineNumber, TextNode|CommandNode $opening, TextNode|CommandNode $closing)
    {
        parent::__construct($lineNumber, $opening, $closing);
    }
}
