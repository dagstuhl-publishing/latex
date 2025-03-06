<?php

namespace Dagstuhl\Latex\Scanner;

class LatexEnvCommandChunk extends LatexCommandChunk
{
    public string $envName;

    public function __construct(int $lineNo, string $raw, string $command, string $envName)
    {
        parent::__construct($lineNo, $raw, $command);
        $this->envName = $envName;
    }

    public function isEnvCommand(string|array|null $commands = null, string|array|null $envNames = null): bool
    {
        if(is_string($commands)) {
            $commands = [ $commands ];
        }
        if(is_string($envNames)) {
            $envNames = [ $envNames ];
        }
        return ($commands === null || in_array($this->command, $commands)) && ($envNames === null || in_array($this->envName, $envNames));
    }
}
