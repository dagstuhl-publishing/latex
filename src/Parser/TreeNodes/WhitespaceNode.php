<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;

class WhitespaceNode extends TextNode
{
    public function getText(bool $trim = false): string
    {
        return $trim ? '' : $this->content;
    }
}

