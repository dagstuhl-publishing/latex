<?php

namespace Dagstuhl\Latex\Parser;

use Exception;

class ParseException extends Exception
{
    public function __construct(string $message, public int $lineNumber, ?ParseTreeNode $contextNode = null) {
        $fullMessage = "Parse Error [Line {$lineNumber}]: {$message}";

        if ($contextNode !== null) {
            $fullMessage .= "\n\nTree Structure at time of error:\n";
            $fullMessage .= $contextNode->toTreeString();
        }

        parent::__construct($fullMessage);
    }
}