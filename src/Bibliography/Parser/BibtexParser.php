<?php

namespace Dagstuhl\Latex\Bibliography\Parser;

/**
 * A BibTeX parser: recursive descent over BibtexScanner.
 *
 * Written to replace Dagstuhl\Latex\Bibliography\ParseEntries, whose edge cases
 * come from locating values by string search inside the raw entry. Here a value
 * is whatever the scanner reads between its delimiters, so "key = {a}" and
 * 'key = "a"' and "key = jan # { 2020}" are three cases of one rule rather than
 * three patterns that must agree.
 *
 * Parsing is in two passes: the structure first, keeping each value as the
 * pieces it is made of, then the @string table, then the pieces resolved
 * against it. That is what lets an entry use a macro defined further down the
 * file, which BibTeX itself would not allow but real files rely on.
 *
 * Defaults are the sane ones. ParseEntriesCompat turns on legacyMode(), which
 * reproduces the old parser's behaviour point by point; the quirks live there
 * rather than being scattered here.
 */
final class BibtexParser
{
    private const ENTRY_TYPE = 'bibtexEntryType';

    private const CITATION = 'bibtexCitation';

    private bool $expandStrings = true;

    private bool $contractWhitespace = true;

    private bool $legacy = false;

    /** @var array<string, string> */
    private array $seeded = [];

    /** @var array<string, string> */
    private array $strings = [];

    /**
     * Undefined macro names as keys, so that a file which parses badly cannot
     * grow this without bound - and so that asking whether one was seen before
     * does not get slower with every one that was.
     *
     * @var array<string, true>
     */
    private array $undefined = [];

    /** @var BibtexDiagnostic[] */
    private array $diagnostics = [];

    /**
     * Substitute @string macros into the values that reference them.
     *
     * Off leaves the macro's name in place; delimiters are dropped either way,
     * since those are syntax rather than content.
     */
    public function expandStrings(bool $expand = true): self
    {
        $this->expandStrings = $expand;

        return $this;
    }

    /**
     * Collapse runs of whitespace in a value to one space.
     *
     * An output transform, applied once the value has been read. The old parser
     * ran the same idea over the raw entry *before* splitting it, so the regex
     * tidying values also mangled the structure about to be parsed.
     *
     * Any run of whitespace becomes one space, newlines included. That is what
     * the old parser amounts to as well, although it gets there by a different
     * route: extractEntries() rejoins the file line by line with a space, so no
     * newline ever reaches its /\s\s+/ regex.
     */
    public function contractWhitespace(bool $contract = true): self
    {
        $this->contractWhitespace = $contract;

        return $this;
    }

    /**
     * Macros known before the file is read - journal abbreviations, month
     * names. A @string in the file overrides one of the same name.
     *
     * @param  array<string, string>  $strings
     */
    public function withStrings(array $strings): self
    {
        $this->seeded = array_change_key_case($strings, CASE_LOWER);

        return $this;
    }

    /**
     * Behave as ParseEntries did, quirk for quirk: an undefined macro empties
     * the field, an undefined macro already seen keeps its name instead, and a
     * malformed entry carries a made-up field saying so.
     *
     * For ParseEntriesCompat alone - nothing else should want this.
     */
    public function legacyMode(bool $legacy = true): self
    {
        $this->legacy = $legacy;

        return $this;
    }

    public function parse(string $source): BibtexDocument
    {
        $this->strings = $this->seeded;
        $this->undefined = [];
        $this->diagnostics = [];

        [$rawEntries, $stringDefinitions, $preambles] = $this->collect($source);

        foreach ($stringDefinitions as [$name, $pieces, $line]) {
            // not contracted, and above all not trimmed: a macro written for
            // concatenation carries its own spacing - proc = "Proc.~of the "
            // followed by ipdps = " Int. Symposium …" only reads correctly
            // while both keep the space they were given. Contraction belongs to
            // the assembled field value, not to its parts.
            $this->strings[$name] = $this->resolve($pieces, $line, null, contract: false);
        }

        // an entry still holding unresolved fields is finished here; the rest
        // were finished as they were read. Resolved in place and freed one at a
        // time, so a large bibliography never holds both forms of every entry.
        foreach (array_keys($rawEntries) as $key) {
            if (isset($rawEntries[$key]['fields'])) {
                $rawEntries[$key] = $this->resolveEntry($rawEntries[$key]);
            }
        }

        $entries = $rawEntries;

        $preamble = '';

        foreach ($preambles as [$pieces, $line]) {
            // not contracted: a preamble is LaTeX to be emitted as written, not
            // a bibliographic value, and several of them concatenate verbatim
            $preamble .= $this->resolve($pieces, $line, null, contract: false);
        }

        return new BibtexDocument(
            $entries,
            $this->strings,
            $preamble,
            array_keys($this->undefined),
            $this->diagnostics
        );
    }

    /**
     * First pass: find every entry and keep its values unresolved.
     *
     * Outside an entry everything is a comment - BibTeX's own rule - so the
     * loop is "find an @, read what follows it, repeat".
     *
     * @return array{0: array<int, array{type: string, citation: string, fields: array<string, string|array>, line: int}>, 1: array<int, array{0: string, 1: string|array, 2: int}>, 2: array<int, array{0: string|array, 1: int}>}
     */
    private function collect(string $source): array
    {
        $scanner = new BibtexScanner($source);
        $entries = $strings = $preambles = [];

        while ($scanner->skipTo('@')) {
            $line = $scanner->line();
            $at = $scanner->position();
            $scanner->next();
            $scanner->skipWhitespace();

            $type = strtolower(trim($scanner->readName()));
            $scanner->skipWhitespace();

            $opening = $scanner->peek();

            if ($opening !== '{' && $opening !== '(') {
                $this->diagnostics[] = BibtexDiagnostic::warning(
                    $type === '' ? 'Stray "@" outside an entry.' : '"@'.$type.'" is not followed by a delimiter.',
                    $line
                );

                continue;
            }

            $scanner->next();

            $bodyLine = $scanner->line();
            $body = $scanner->readEntryBody($opening === '{' ? '}' : ')');

            if ($body === null) {
                $this->diagnostics[] = BibtexDiagnostic::error(
                    '"@'.$type.'" is never closed; looking for the next entry.',
                    $line
                );

                // carry on from just past the "@" that opened it rather than
                // giving up on the file: one entry whose braces do not add up
                // must not cost every entry below it
                $scanner->seek($at + 1);

                continue;
            }

            match (true) {
                $type === 'string' => $this->collectString($body, $bodyLine, $strings),
                $type === 'preamble' => $preambles[] = [
                    $this->readValue(new BibtexScanner($body, $bodyLine), null),
                    $bodyLine,
                ],
                $type === 'comment' => null,
                default => $this->collectEntry($type, $body, $bodyLine, $entries),
            };
        }

        return [$entries, $strings, $preambles];
    }

    private function collectString(string $body, int $line, array &$strings): void
    {
        $scanner = new BibtexScanner($body, $line);
        $scanner->skipWhitespace();

        $name = strtolower(trim($scanner->readName()));
        $scanner->skipWhitespace();

        if ($name === '' || ! $scanner->accept('=')) {
            $this->diagnostics[] = BibtexDiagnostic::warning('A @string without a name or a value.', $line);

            return;
        }

        $strings[] = [$name, $this->readValue($scanner, null), $line];
    }

    private function collectEntry(string $type, string $body, int $line, array &$entries): void
    {
        $scanner = new BibtexScanner($body, $line);
        $scanner->skipWhitespace();

        $citation = $this->readCitation($scanner);

        $fields = [];

        while (true) {
            $scanner->skipWhitespace();

            if ($scanner->atEnd()) {
                break;
            }

            $fieldAt = $scanner->position();
            $name = strtolower(trim($scanner->readName()));
            $scanner->skipWhitespace();

            if ($name === '') {
                $this->diagnostics[] = BibtexDiagnostic::warning(
                    'Skipped something that is not a field.',
                    $scanner->line($fieldAt),
                    $citation
                );

                break;
            }

            if (! $scanner->accept('=')) {
                $this->diagnostics[] = BibtexDiagnostic::warning(
                    'Field "'.$name.'" has no "=".',
                    $scanner->line($fieldAt),
                    $citation
                );

                break;
            }

            if (array_key_exists($name, $fields)) {
                $this->diagnostics[] = BibtexDiagnostic::warning(
                    'Field "'.$name.'" appears twice; the later one wins.',
                    $scanner->line($fieldAt),
                    $citation
                );
            }

            $fields[$name] = $this->readValue($scanner, $citation);

            $scanner->skipWhitespace();

            if (! $scanner->accept(',')) {
                break;
            }
        }

        // a value containing a macro is the only thing the second pass can
        // change, so an entry without one is finished already - which is most
        // of them, and is what keeps a 38 MB bibliography out of swap
        foreach ($fields as $value) {
            if (is_array($value)) {
                $entries[] = ['type' => $type, 'citation' => $citation, 'fields' => $fields, 'line' => $line];

                return;
            }
        }

        $entry = [self::ENTRY_TYPE => $type, self::CITATION => $citation];

        foreach ($fields as $name => $value) {
            $entry[$name] = $this->contract($value);
        }

        $entries[] = $entry;
    }

    /**
     * The key before the first comma - unless what stands there is a field, as
     * in @book{author = {…}}, which BibTeX allows and which means the entry has
     * no key at all.
     */
    private function readCitation(BibtexScanner $scanner): string
    {
        $start = $scanner->position();
        $name = $scanner->readCitationKey();
        $scanner->skipWhitespace();

        if ($scanner->peek() === '=') {
            $scanner->seek($start);

            return '';
        }

        if ($scanner->accept(',')) {
            return trim($name);
        }

        return trim($name);
    }

    /**
     * A value is one or more pieces joined by '#'. Each piece is kept as its
     * kind and its text, so the second pass can tell a macro name from a word
     * that happens to look like one.
     *
     * @return string|array{pieces: array<int, array{0: string, 1: string}>, line: int}
     *                                                                                  a plain string once it is already final - see below
     */
    private function readValue(BibtexScanner $scanner, ?string $citation): string|array
    {
        $at = $scanner->position();
        $pieces = [];
        $hasMacro = false;

        while (true) {
            $scanner->skipWhitespace();

            $character = $scanner->peek();

            if ($character === '{') {
                $group = $scanner->readBraceGroup();

                if ($group === null) {
                    $this->diagnostics[] = BibtexDiagnostic::error(
                        'A value is never closed.',
                        $scanner->line($at),
                        $citation
                    );

                    break;
                }

                $pieces[] = ['text', $group];
            } elseif ($character === '"') {
                $quoted = $scanner->readQuotedString();

                if ($quoted === null) {
                    $this->diagnostics[] = BibtexDiagnostic::error(
                        'A quoted value is never closed.',
                        $scanner->line($at),
                        $citation
                    );

                    break;
                }

                $pieces[] = ['text', $quoted];
            } elseif ($character !== null && ctype_digit($character)) {
                $pieces[] = ['text', $scanner->readNumber()];
            } else {
                $name = $scanner->readName();

                if ($name === '') {
                    break;
                }

                $pieces[] = ['macro', trim($name)];
                $hasMacro = true;
            }

            $scanner->skipWhitespace();

            if (! $scanner->accept('#')) {
                break;
            }
        }

        // the overwhelming majority of values are a single braced group with no
        // macro in them, and nothing about those can change in the second pass.
        // Keeping them as one string rather than as an array of pieces is what
        // makes a 38 MB bibliography fit in memory.
        if (! $hasMacro) {
            return implode('', array_column($pieces, 1));
        }

        return ['pieces' => $pieces, 'line' => $scanner->line($at)];
    }

    private function resolveEntry(array $raw): array
    {
        $entry = [
            self::ENTRY_TYPE => $raw['type'],
            self::CITATION => $raw['citation'],
        ];

        foreach ($raw['fields'] as $name => $value) {
            $entry[$name] = $this->resolve($value, is_array($value) ? $value['line'] : $raw['line'], $raw['citation']);
        }

        return $entry;
    }

    /** @param string|array{pieces: array, line: int} $value */
    private function resolve(string|array $value, int $line, ?string $citation, bool $contract = true): string
    {
        if (is_string($value)) {
            return $contract ? $this->contract($value) : $value;
        }

        $resolved = '';

        foreach ($value['pieces'] as [$kind, $text]) {
            if ($kind === 'text') {
                $resolved .= $text;

                continue;
            }

            // delimiters are syntax and always go; a macro is a substitution
            // and only goes when asked for. The old parser draws the line in
            // the same place: removeDelimit is on whatever expandMacro says.
            if (! $this->expandStrings) {
                $resolved .= $text;

                continue;
            }

            $key = strtolower($text);

            if (array_key_exists($key, $this->strings)) {
                $resolved .= $this->strings[$key];

                continue;
            }

            $seenBefore = isset($this->undefined[$text]);
            $this->undefined[$text] = true;

            // once per name, not once per use: a file the scanner loses its
            // place in can reference the same name a million times, and a
            // diagnostic object for each is how this ran out of memory
            if (! $seenBefore) {
                $this->diagnostics[] = BibtexDiagnostic::warning(
                    'Undefined string "'.$text.'".',
                    $line,
                    $citation
                );
            }

            // the old parser dropped the field here, which turns a mistyped
            // @string into a silently missing journal name - and only the
            // first time, since removeDelimiters() returns the name unchanged
            // once it is already in its undefined list
            $resolved .= $this->legacy && ! $seenBefore ? '' : $text;
        }

        return $contract ? $this->contract($resolved) : $resolved;
    }

    private function contract(string $value): string
    {
        if (! $this->contractWhitespace) {
            return trim($value);
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
