<?php

namespace Dagstuhl\Latex\Parser\TreeNodes;


use Dagstuhl\Latex\Parser\ParseTreeNode;

class RootNode extends ParseTreeNode
{
    public function __construct()
    {
        parent::__construct(1);
    }
}
