<?php

namespace Dagstuhl\Latex\Scanner;

class LatexScanner
{
    private string $source;
    private int $lineNo;
    private string $remaining;

    public function __construct(string $source)
    {
        $this->source = $source;
        $this->reset();
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getRemaining(): string
    {
        return $this->remaining;
    }

    public function getLineNo(): int
    {
        return $this->lineNo;
    }

    public function reset()
    {
        $this->lineNo = 1;
        $this->remaining = $this->source;
    }

    public function getState(): mixed
    {
        return [ $this->lineNo, $this->remaining ];
    }

    public function restoreState(mixed $state)
    {
        list($this->lineNo, $this->remaining) = $state;
    }

    public function take(int $count): string
    {
        $text = substr($this->remaining, 0, $count);
        $this->incrementLineNo($text);
        $this->remaining = substr($this->remaining, $count);
        return $text;
    }

    public function readChunk(): ?LatexChunk
    {
        $lineNo = $this->lineNo;

        if($this->remaining === '') {
            return null;

        } else if(preg_match('/^(%([^\\r\\n]*)\\r?\\n?)(.*)$/s', $this->remaining, $matches)) {
            list(, $line, $comment, $remaining) = $matches;

            $this->incrementLineNo($line);
            $this->remaining = $remaining;
            return new LatexCommentChunk($lineNo, $line, $comment);

        } else if(preg_match('/^(\\\\[0-9]|\\\\[a-zA-Z@]+)(.*)$/s', $this->remaining, $matches)) {
            list(, $text, $remaining) = $matches;

            $this->incrementLineNo($text);
            $this->remaining = $remaining;
            $command = ltrim($text, '\\');

            if($command === 'begin' || $command === 'end') {
                if(($arg = $this->readNonOptArgument()) !== null) {
                    $envName = trim($arg->body);
                    return new LatexEnvCommandChunk($lineNo, $text.$arg->raw, $command, $envName);
                }
            }

            return new LatexCommandChunk($lineNo, $text, $command);

        } else {
            $text = '';

            // We use a loop here because matching the regular expression below runs into an error ("JIT stack limit
            // exhausted") when there is a long chunk of text without any backslash character.

            while($this->remaining !== '') {
                if(preg_match('/^((?:[^\%\\\\]|\\\\[^a-zA-Z0-9@]|\\\\$)*)(.*)$/s', $this->remaining, $matches)) {
                    list(, $text2, $remaining) = $matches;

                    $text .= $text2;
                    $this->incrementLineNo($text2);
                    $this->remaining = $remaining;
                    break;

                } else {
                    $text2 = substr($this->remaining, 0, min(strlen($this->remaining), 4096));
                    if(str_ends_with($text2, '\\') && !str_ends_with($text2, '\\\\')) {
                        $text2 = substr($text2, 0, strlen($text2) - 1);
                    }
                    $remaining = substr($this->remaining, strlen($text2));

                    $text .= $text2;
                    $this->incrementLineNo($text2);
                    $this->remaining = $remaining;
                }
            }

            return new LatexTextChunk($lineNo, $text, $text); //TODO unescape
        }
    }

    public function readOptArgument(): ?LatexArgumentChunk
    {
        return $this->readArgument(true, false);
    }

    public function readNonOptArgument(): ?LatexArgumentChunk
    {
        return $this->readArgument(false, true);
    }

    public function readArgument(bool $allowOpt = true, bool $allowNonOpt = true): ?LatexArgumentChunk
    {
        $remaining = ltrim($this->remaining);
        if($remaining === '') {
            return null;
        }
        $skipped = strlen($this->remaining) - strlen($remaining);
        $delim = $remaining[0] ?? '';

        if($delim === '\\') {
            if(!$allowNonOpt) {
                return null;
            }
            if(!preg_match('/^(\\\\[0-9]|\\\\[a-zA-Z@]+)(.*)$/s', $this->remaining, $matches)) {
                return null;
            }

            $optional = false;
            $braces = null;
            $i = $skipped + strlen($matches[1]);

        } else {
            if($delim === '{') {
                if(!$allowNonOpt) {
                    return null;
                }
                $optional = false;
                $braces = '{}';
            } else if($delim === '[') {
                if(!$allowOpt) {
                    return null;
                }
                $optional = true;
                $braces = '[]';
            } else {
                return null;
            }

            $depth = 1;
            $escape = false;
            for($i = $skipped + 1; $depth > 0 && $i < strlen($this->remaining); $i++) {
                if($escape) {
                    $escape = false;
                    continue;
                }
                $ch = $this->remaining[$i];
                if($ch === $braces[0]) {
                    $depth++;
                } else if($ch === $braces[1]) {
                    $depth--;
                } else if($ch === '\\') {
                    $escape = true;
                }
            }

            if($depth > 0) {
                return null;
            }
        }

        $lineNo = $this->lineNo;
        $arg = substr($this->remaining, 0, $i);
        $this->incrementLineNo($arg);
        $this->remaining = substr($this->remaining, $i);

        $body = substr($arg, 1, strlen($arg) - 2);
        return new LatexArgumentChunk($lineNo, $arg, $optional, $braces, $body);
    }

    public function readEnv(?LatexEnvCommandChunk $begin): ?LatexEnvironmentChunk
    {
        if($begin === null) {
            if(preg_match('/^\\\\begin[ {].*$', $this->remaining)) {
                $begin = $this->readChunk();
            }
        }

        $stateBefore = $this->getState();

        $lineNo = $this->lineNo;
        $body = '';
        $depth = 1;

        while($depth > 0) {
            $chunk = $this->readChunk();

            if($chunk === null) {
                $this->restoreState($stateBefore);
                return null;
            }

            if($chunk->isEnvCommand() && $chunk->envName === $begin->envName) {
                if($chunk->command === 'begin') {
                    $depth++;
                } else if($chunk->command === 'end') {
                    $depth--;
                }
            }

            if($depth > 0) {
                $body .= $chunk->raw;
            }
        }

        $end = $chunk;

        return new LatexEnvironmentChunk($lineNo, $begin->raw.$body.$end->raw, $begin->envName, $begin, $body, $end);
    }

    private function incrementLineNo(string $chunk)
    {
        $this->lineNo += preg_match_all('/(\\r\\n|\\r|\\n)/', $chunk);
    }
}
