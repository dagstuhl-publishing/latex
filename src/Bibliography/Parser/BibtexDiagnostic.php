<?php

namespace Dagstuhl\Latex\Bibliography\Parser;

/**
 * Something the parser could not make sense of, reported rather than patched.
 *
 * The parser it replaces repaired entries by injecting a made-up field, which
 * left the problem inside the data instead of beside it.
 */
final class BibtexDiagnostic
{
    public const WARNING = 'warning';

    public const ERROR = 'error';

    public function __construct(
        public readonly string $severity,
        public readonly string $message,
        public readonly int $line,
        public readonly ?string $citation = null,
    ) {}

    public static function warning(string $message, int $line, ?string $citation = null): self
    {
        return new self(self::WARNING, $message, $line, $citation);
    }

    public static function error(string $message, int $line, ?string $citation = null): self
    {
        return new self(self::ERROR, $message, $line, $citation);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (line %d%s): %s',
            strtoupper($this->severity),
            $this->line,
            $this->citation !== null ? ', '.$this->citation : '',
            $this->message
        );
    }
}
