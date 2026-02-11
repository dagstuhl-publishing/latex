<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

class EnvironmentNode extends EnvelopeNode
{
    private string $name;

    public function __construct(public string $envName, CommandNode $opening, ?CommandNode $closing)
    {
        parent::__construct($opening->lineNumber, $opening, $closing);
        $this->name = $envName;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
