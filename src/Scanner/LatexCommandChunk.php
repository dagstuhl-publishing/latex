<?php

namespace Dagstuhl\Latex\Scanner;

class LatexCommandChunk extends LatexChunk
{
    public string $command;

    public function __construct(int $lineNo, string $raw, string $command)
    {
        parent::__construct($lineNo, $raw);
        $this->command = $command;
    }

    public function isCommand(string|array|null $commands = null): bool
    {
        if(is_string($commands)) {
            $commands = [ $commands ];
        }
        return $commands === null || in_array($this->command, $commands);
    }
}
