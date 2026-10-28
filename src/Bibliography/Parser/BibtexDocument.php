<?php

namespace Dagstuhl\Latex\Bibliography\Parser;

/**
 * What one .bib file turned out to contain.
 *
 * Entries are plain arrays rather than objects, because that is what
 * Dagstuhl\Latex\Bibliography\BibEntry takes: each carries its fields plus
 * bibtexEntryType and bibtexCitation, the two names the old parser invented and
 * everything downstream now reads.
 */
final class BibtexDocument
{
    /**
     * @param  array<int, array<string, string>>  $entries
     * @param  array<string, string>  $strings
     * @param  string[]  $undefinedStrings
     * @param  BibtexDiagnostic[]  $diagnostics
     */
    public function __construct(
        private readonly array $entries,
        private readonly array $strings,
        private readonly string $preamble,
        private readonly array $undefinedStrings,
        private readonly array $diagnostics,
    ) {}

    /** @return array<int, array<string, string>> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return array<string, string> name => value, file definitions over seeded ones */
    public function strings(): array
    {
        return $this->strings;
    }

    /** Every @preamble of the file, concatenated in the order they appeared. */
    public function preamble(): string
    {
        return $this->preamble;
    }

    /** @return string[] macros referenced but never defined */
    public function undefinedStrings(): array
    {
        return $this->undefinedStrings;
    }

    /** @return BibtexDiagnostic[] */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function hasErrors(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->severity === BibtexDiagnostic::ERROR) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, string>> entries by citation key */
    public function entriesByCitation(): array
    {
        $byCitation = [];

        foreach ($this->entries as $entry) {
            $byCitation[$entry['bibtexCitation'] ?? ''] = $entry;
        }

        return $byCitation;
    }
}
