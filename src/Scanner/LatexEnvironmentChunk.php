<?php

namespace Dagstuhl\Latex\Scanner;

class LatexEnvironmentChunk extends LatexChunk
{
    public string $envName;

    public LatexEnvCommandChunk $begin;

    public string $body;

    public LatexEnvCommandChunk $end;

    public function __construct(int $lineNo, string $raw, string $envName, LatexEnvCommandChunk $begin, string $body, LatexEnvCommandChunk $end)
    {
        parent::__construct($lineNo, $raw);
        $this->envName = $envName;
        $this->begin = $begin;
        $this->body = $body;
        $this->end = $end;
    }

    public function isEnvironment(string|array|null $envNames = null): bool
    {
        if(is_string($envNames)) {
            $envNames = [ $envNames ];
        }
        return $envNames === null || in_array($this->envName, $envNames);
    }
}
