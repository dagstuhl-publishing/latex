<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;
class MathNode extends EnvelopeNode
{
    public function __construct(int $lineNumber, ParseTreeNode $opening, ParseTreeNode $closing)
    {
        parent::__construct($lineNumber, $opening, $closing);
    }
}
