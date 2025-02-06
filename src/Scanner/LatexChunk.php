<?php

namespace Dagstuhl\Latex\Scanner;

abstract class LatexChunk
{
    public int $lineNo;

    public string $raw;

    public function __construct(int $lineNo, string $raw)
    {
        $this->lineNo = $lineNo;
        $this->raw = $raw;
    }

    public function isVerb(): bool
    {
        return false;
    }

    public function isComment(): bool
    {
        return false;
    }

    public function isText(): bool
    {
        return false;
    }

    public function isArgument(): bool
    {
        return false;
    }

    public function isCommand(string|array|null $commands = null): bool
    {
        return false;
    }

    public function isEnvCommand(string|array|null $commands = null, string|array|null $envNames = null): bool
    {
        return false;
    }

    public function isEnvironment(string|array|null $envNames = null): bool
    {
        return false;
    }
}
