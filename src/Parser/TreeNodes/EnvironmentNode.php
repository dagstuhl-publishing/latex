<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

class EnvironmentNode extends EnvelopeNode
{
    private string $name;

    public function __construct(int $lineNumber, public string $envName, CommandNode $opening, CommandNode $closing)
    {
        parent::__construct($lineNumber, $opening, $closing);
        $this->name = $envName;
    }
}
