<?php

namespace Dagstuhl\Latex\Bibliography\Parser;

/**
 * A cursor over BibTeX source, with the primitives the grammar needs.
 *
 * Everything that makes BibTeX awkward to parse with regular expressions lives
 * here: braces nest, a quoted value may contain braces which may contain a
 * quote, and a delimiter only closes when nothing is open around it. Expressed
 * as a cursor these are three loops rather than a set of patterns that have to
 * agree with one another.
 */
final class BibtexScanner
{
    private int $position = 0;

    private readonly int $length;

    /**
     * $baseLine lets a scanner over an entry body still report file lines: the
     * body is a slice, so its own line 1 is wherever the entry began.
     */
    public function __construct(
        private readonly string $source,
        private readonly int $baseLine = 1,
    ) {
        $this->length = strlen($source);
    }

    public function atEnd(): bool
    {
        return $this->position >= $this->length;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function peek(): ?string
    {
        return $this->atEnd() ? null : $this->source[$this->position];
    }

    public function next(): ?string
    {
        return $this->atEnd() ? null : $this->source[$this->position++];
    }

    /** 1-based, for diagnostics; counted on demand rather than tracked. */
    public function line(?int $position = null): int
    {
        return substr_count($this->source, "\n", 0, min($position ?? $this->position, $this->length)) + $this->baseLine;
    }

    public function seek(int $position): void
    {
        $this->position = max(0, min($position, $this->length));
    }

    public function slice(int $from, int $to): string
    {
        return substr($this->source, $from, max(0, $to - $from));
    }

    public function skipWhitespace(): void
    {
        while (! $this->atEnd() && ctype_space($this->source[$this->position])) {
            $this->position++;
        }
    }

    /** Consumes the character when it is the one expected. */
    public function accept(string $character): bool
    {
        if ($this->peek() === $character) {
            $this->position++;

            return true;
        }

        return false;
    }

    /** Advances to the next occurrence, leaving the cursor on it. */
    public function skipTo(string $character): bool
    {
        $found = strpos($this->source, $character, $this->position);

        $this->position = $found === false ? $this->length : $found;

        return $found !== false;
    }

    public function readWhile(callable $predicate): string
    {
        $start = $this->position;

        while (! $this->atEnd() && $predicate($this->source[$this->position])) {
            $this->position++;
        }

        return $this->slice($start, $this->position);
    }

    /**
     * An entry type, a macro name or a field name.
     *
     * Defined by what ends it rather than by what it may contain: BibTeX allows
     * nearly anything here, and a list of permitted characters would reject a
     * key some generator has produced rather than read it.
     */
    public function readName(): string
    {
        return $this->readWhile(
            static fn (string $c): bool => ! ctype_space($c) && ! str_contains('{}()",=#%', $c)
        );
    }

    public function readNumber(): string
    {
        return $this->readWhile(static fn (string $c): bool => ctype_digit($c));
    }

    /**
     * The text inside a brace group, delimiters dropped, cursor left after the
     * closing brace. Null when the group never closes.
     *
     * Braces are counted literally, backslashes and all - which is what BibTeX
     * itself does. Treating \{ and \} as escapes looks more careful and is
     * worse: well-formed LaTeX pairs them, so the depth comes out the same
     * either way, while a value ending "M.\}" - a stray backslash, which real
     * files contain - would never close and would swallow the rest of the file.
     */
    public function readBraceGroup(): ?string
    {
        if ($this->peek() !== '{') {
            return null;
        }

        $start = ++$this->position;
        $depth = 1;

        while (! $this->atEnd()) {
            $character = $this->source[$this->position++];

            if ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth === 0) {
                return $this->slice($start, $this->position - 1);
            }
        }

        return null;
    }

    /**
     * The text inside "...", delimiters dropped. Braces still nest inside it,
     * and a quote within a group does not end the value - title = "A {"} B" is
     * one value, which is why this cannot be a regular expression.
     */
    public function readQuotedString(): ?string
    {
        if ($this->peek() !== '"') {
            return null;
        }

        $start = ++$this->position;
        $depth = 0;

        while (! $this->atEnd()) {
            $character = $this->source[$this->position++];

            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth = max(0, $depth - 1);
            } elseif ($character === '"' && $depth === 0) {
                return $this->slice($start, $this->position - 1);
            }
        }

        return null;
    }

    /**
     * Everything up to the delimiter that closes the entry, which only closes
     * while no brace group and no quoted string is open around it.
     *
     * Null when it never closes - a truncated file, which the parser reports
     * rather than guessing at.
     */
    public function readEntryBody(string $closing): ?string
    {
        $start = $this->position;
        $depth = 0;
        $quoted = false;

        while (! $this->atEnd()) {
            $character = $this->source[$this->position++];

            if ($character === '"' && $depth === 0) {
                $quoted = ! $quoted;

                continue;
            }

            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                if ($depth === 0 && $closing === '}' && ! $quoted) {
                    return $this->slice($start, $this->position - 1);
                }

                $depth = max(0, $depth - 1);
            } elseif ($character === $closing && $depth === 0 && ! $quoted) {
                return $this->slice($start, $this->position - 1);
            }
        }

        return null;
    }
}
