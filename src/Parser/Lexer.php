<?php

namespace Dagstuhl\Latex\Parser;

/**
 * A private utility for the Lexer to track character-to-source mapping.
 */
class Char
{
    public int $charCode;
    public int $lineNumber;
    public int $charStart;
    public int $charEnd;

    public function __construct(int $charCode, int $lineNumber, int $charStart, int $charEnd)
    {
        $this->charCode   = $charCode;
        $this->lineNumber = $lineNumber;
        $this->charStart  = $charStart;
        $this->charEnd    = $charEnd;
    }
}

class Lexer
{
    private string $source;
    private int $pos = 0;
    private int $len;
    private int $lineNumber = 1;
    private array $catCodes = [];
    private string $encoding = '';

    public function __construct(string $source, string $encoding = 'ascii', bool $allowWhitespaceInText = true)
    {
        $this->source = $source;
        $this->len = strlen($source);
        $this->setEncoding($encoding);
        $this->setAllowWhitespaceInText($allowWhitespaceInText);
    }

    public function next(): ?Token
    {
        $char = $this->getChar();
        if ($char === null) {
            return null;
        }

        $startByte = $char->charStart;
        $startLine = $char->lineNumber;
        $code = $this->getCatcode($char->charCode);

        // Brackets are unique: they are Category 12 (Other) but act as structural delimiters
        if ($char->charCode === 91) {
            return new Token(TokenType::OPT_OPEN, $startByte, $this->pos, $startLine);
        }
        if ($char->charCode === 93) {
            return new Token(TokenType::OPT_CLOSE, $startByte, $this->pos, $startLine);
        }

        $type = match ($code) {
            0 => $this->consumeCommand(),
            1 => TokenType::GROUP_OPEN,
            2 => TokenType::GROUP_CLOSE,
            3 => $this->handleMathShift(),
            4 => TokenType::ALIGN_TAB,
            10 => $this->consumeWhitespace(),
            13 => TokenType::COMMAND,
            14 => $this->consumeComment(),
            default => $this->consumeText(),
        };

        return new Token($type, $startByte, $this->pos, $startLine);
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function setEncoding(string $encoding): void
    {
        $normalized = strtolower(str_replace('-', '', $encoding));

        if (empty($this->catCodes)) {
            $this->catCodes = array_fill(0, 256, 12);
            $this->catCodes[92] = 0;   // \
            $this->catCodes[123] = 1;   // {
            $this->catCodes[125] = 2;   // }
            $this->catCodes[36] = 3;   // $
            $this->catCodes[38] = 4;   // &
            $this->catCodes[94] = 7;   // ^
            $this->catCodes[95] = 8;   // _
            $this->catCodes[32] = 10;  // Space
            $this->catCodes[9] = 10;  // Tab
            $this->catCodes[10] = 10;  // LF
            $this->catCodes[126] = 13;  // ~
            $this->catCodes[37] = 14;  // %

            foreach (range(65, 90) as $i) $this->catCodes[$i] = 11;
            foreach (range(97, 122) as $i) $this->catCodes[$i] = 11;
        }

        switch ($normalized) {
            case 'ascii':
            case 'latin1':
                foreach (range(128, 255) as $b) $this->catCodes[$b] = 12;
                break;
            case 'utf8':
                foreach (range(128, 255) as $b) $this->catCodes[$b] = 13;
                break;
            default:
                throw new ParseException("Unsupported encoding: " . $encoding, $this->lineNumber);
        }

        $this->encoding = $normalized;
    }

    public function getCatcode(int $charCode): int
    {
        return $this->catCodes[$charCode] ?? 12;
    }

    public function setCatCode(int $codePoint, int $code): void
    {
        $this->catCodes[$codePoint] = $code;
    }

    public function allowsWhitespaceInText(): bool
    {
        return $this->allowWhitespaceInText;
    }

    public function setAllowWhitespaceInText(bool $allow): void
    {
        $this->allowWhitespaceInText = $allow;
    }

    public function getCurrentByte(): ?int
    {
        if ($this->pos >= $this->len) return null;
        return ord($this->source[$this->pos]);
    }

    public function getChar(): ?Char
    {
        if ($this->pos >= $this->len) {
            return null;
        }

        $start = $this->pos;
        $byte = ord($this->source[$this->pos]);
        $charCode = $byte;
        $charLen = 1;

        if ($this->encoding === 'utf8' && $byte >= 128) {
            if (($byte & 0xE0) === 0xC0) $charLen = 2;
            elseif (($byte & 0xF0) === 0xE0) $charLen = 3;
            elseif (($byte & 0xF8) === 0xF0) $charLen = 4;

            $charLen = min($charLen, $this->len - $this->pos);
            $sub = substr($this->source, $this->pos, $charLen);
            $charCode = mb_ord($sub, 'UTF-8');
        }

        $this->pos += $charLen;
        $char = new Char($charCode, $this->lineNumber, $start, $this->pos);

        // Increment line number BEFORE returning the Char object
        if ($byte === 10) $this->lineNumber++;

        return $char;
    }

    private function handleMathShift(): TokenType
    {
        $byte = $this->getCurrentByte();
        if ($byte !== null && $this->getCatcode($byte) === 3) {
            $this->getChar();
        }
        return TokenType::MATH_TOGGLE;
    }

    private function consumeText(): TokenType
    {
        while (($byte = $this->getCurrentByte()) !== null) {
            $code = $this->getCatcode($byte);

            // Stop if it's not a text-like category or if it's a structural bracket
            if (!in_array($code, [7, 8, 11, 12]) || $byte === 91 || $byte === 93) {
                break;
            }

            // Stop if we hit whitespace and it is not allowed in text tokens
            if (!$this->allowWhitespaceInText && $code === 10) {
                break;
            }

            $this->getChar();
        }

        return TokenType::TEXT;
    }

    private function consumeCommand(): TokenType
    {
        $byte = $this->getCurrentByte();
        if ($byte === null) return TokenType::COMMAND;

        // Control Symbol vs Word: If the first char is a letter,
        // keep consuming until we hit a non-letter.
        if ($this->getCatcode($byte) === 11) {
            do {
                $this->getChar();
                $next = $this->getCurrentByte();
            } while ($next !== null && $this->getCatcode($next) === 11);
        } else {
            // Control Symbol: just consume the one character
            $this->getChar();
        }

        return TokenType::COMMAND;
    }

    private function consumeWhitespace(): TokenType
    {
        while (($byte = $this->getCurrentByte()) !== null) {
            if ($this->getCatcode($byte) !== 10) break;
            $this->getChar();
        }
        return TokenType::WHITESPACE;
    }

    private function consumeComment(): TokenType
    {
        while (($char = $this->getChar()) != null) {
            if ($char->charCode === 10) {
                break;
            }
        }
        return TokenType::COMMENT;
    }

    public function consumeRawChar(): array
    {
        $char = $this->getChar();

        if ($char === null) {
            return [null, $this->lineNumber];
        }

        return [substr($this->source, $char->charStart, $char->charEnd - $char->charStart), $char->lineNumber];
    }

    /**
     * @return [consumedString, lineNumber]
     *
     * Note that the $delimiter itself is not included in consumedString.
     */
    public function consumeRawUntil(string $delimiter): array
    {
        $m = strlen($delimiter);
        if ($m === 0) {
            return ["", $this->lineNumber];
        }

        $lastChar = $delimiter[$m - 1];
        $content = "";

        for ($raw = $this->consumeRawChar(); ($rawChar = $raw[0]) !== null; $raw = $this->consumeRawChar()) {
            $content .= $rawChar;

            if ($rawChar === $lastChar && substr($content, -$m) === $delimiter) {
                return [substr($content, 0, -$m), $this->lineNumber];
            }
        }

        throw new ParseException("Unexpected end of input while looking for: '$delimiter'", $this->lineNumber);
    }
}