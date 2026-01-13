<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

use Dagstuhl\Latex\Parser\ParseTreeNode;
use Dagstuhl\Latex\Parser\ParseException;

class ArgumentNode extends EnvelopeNode
{
    public function __construct(int $lineNumber, public bool $isOptional)
    {
        $delims = $isOptional ? ['[', ']'] : ['{', '}'];
        parent::__construct(
            $lineNumber,
            new TextNode($lineNumber, $delims[0]),
            new TextNode($lineNumber, $delims[1])
        );
    }

    public function setClosing(?ParseTreeNode $closing): void
    {
        if ($closing && !($closing instanceof TextNode)) {
            throw new ParseException("The closing node of an EnvironmentNode must be a CommandNode, but is $closing", $closing->lineNumber);
        }
        parent::setClosing($closing);
    }

}
