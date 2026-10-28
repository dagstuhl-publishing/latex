<?php

namespace Dagstuhl\Latex\Tests\Bibtex;

use Dagstuhl\Latex\Bibliography\Parser\BibtexDiagnostic;
use Dagstuhl\Latex\Bibliography\Parser\BibtexParser;
use PHPUnit\Framework\TestCase;

class BibtexParserTest extends TestCase
{
    private function parse(string $source, ?callable $configure = null): array
    {
        $parser = new BibtexParser;

        if ($configure !== null) {
            $configure($parser);
        }

        return [$parser->parse($source), $parser];
    }

    private function entries(string $source, ?callable $configure = null): array
    {
        return $this->parse($source, $configure)[0]->entries();
    }

    private function first(string $source, ?callable $configure = null): array
    {
        return $this->entries($source, $configure)[0];
    }

    // --- the syntax variants -------------------------------------------------

    public function test_it_reads_braced_and_quoted_values_alike(): void
    {
        $entry = $this->first('@article{a, title = {Braced}, journal = "Quoted", year = 2020}');

        $this->assertSame('Braced', $entry['title']);
        $this->assertSame('Quoted', $entry['journal']);
        $this->assertSame('2020', $entry['year']);
    }

    public function test_the_entry_type_and_key_are_carried_under_the_familiar_names(): void
    {
        $entry = $this->first('@InProceedings{smith:2020, title = {x}}');

        $this->assertSame('inproceedings', $entry['bibtexEntryType']);
        $this->assertSame('smith:2020', $entry['bibtexCitation']);
    }

    public function test_field_names_are_lowercased_but_values_are_not(): void
    {
        $entry = $this->first('@article{a, TITLE = {Mixed Case}}');

        $this->assertArrayHasKey('title', $entry);
        $this->assertSame('Mixed Case', $entry['title']);
    }

    public function test_nested_braces_survive_in_a_value(): void
    {
        $entry = $this->first('@article{a, author = {Smith, A. and {Van Dyk}, B.}}');

        $this->assertSame('Smith, A. and {Van Dyk}, B.', $entry['author']);
    }

    /** The case the old parser's delimiter counting had to special-case. */
    public function test_a_quote_inside_braces_inside_a_quoted_value(): void
    {
        $entry = $this->first('@article{a, title = "A {"} B"}');

        $this->assertSame('A {"} B', $entry['title']);
    }

    public function test_parenthesised_entries_are_read_too(): void
    {
        $entry = $this->first('@article(a, title = {Paren}, year = 2020)');

        $this->assertSame('Paren', $entry['title']);
        $this->assertSame('a', $entry['bibtexCitation']);
    }

    public function test_a_trailing_comma_is_allowed(): void
    {
        $entry = $this->first('@article{a, title = {x}, year = 2020,}');

        $this->assertSame('2020', $entry['year']);
    }

    public function test_an_entry_without_a_key_is_read_as_fields(): void
    {
        $entry = $this->first('@book{author = {Nobody}, title = {Keyless}}');

        $this->assertSame('', $entry['bibtexCitation']);
        $this->assertSame('Nobody', $entry['author']);
        $this->assertSame('Keyless', $entry['title']);
    }

    public function test_everything_outside_an_entry_is_a_comment(): void
    {
        $entries = $this->entries(
            "Some stray prose.\n@article{a, title = {x}}\nMore prose, with braces } and an = sign.\n@book{b, title = {y}}"
        );

        $this->assertCount(2, $entries);
        $this->assertSame(['a', 'b'], array_column($entries, 'bibtexCitation'));
    }

    public function test_comment_entries_are_skipped(): void
    {
        $entries = $this->entries('@comment{@article{not:real, title = {x}}} @article{a, title = {y}}');

        $this->assertCount(1, $entries);
        $this->assertSame('a', $entries[0]['bibtexCitation']);
    }

    // --- strings and preamble ------------------------------------------------

    public function test_it_expands_a_string_defined_in_the_file(): void
    {
        $entry = $this->first('@string{acta = {Acta Informatica}} @article{a, journal = acta}');

        $this->assertSame('Acta Informatica', $entry['journal']);
    }

    public function test_it_concatenates_pieces_with_a_hash(): void
    {
        $entry = $this->first('@string{acta = {Acta Informatica}} @article{a, journal = acta # " (Suppl.)" # {!}}');

        $this->assertSame('Acta Informatica (Suppl.)!', $entry['journal']);
    }

    public function test_a_string_may_be_built_from_an_earlier_string(): void
    {
        $entry = $this->first('@string{a = {Acta}} @string{b = a # { Informatica}} @article{x, journal = b}');

        $this->assertSame('Acta Informatica', $entry['journal']);
    }

    /** BibTeX would not allow this; real files do it, so it is read anyway. */
    public function test_an_entry_may_use_a_string_defined_further_down(): void
    {
        $entry = $this->first('@article{a, journal = acta} @string{acta = {Acta Informatica}}');

        $this->assertSame('Acta Informatica', $entry['journal']);
    }

    public function test_seeded_strings_are_used_and_the_file_overrides_them(): void
    {
        $configure = static fn (BibtexParser $p) => $p->withStrings(['acta' => 'Seeded', 'cacm' => 'Communications']);

        $entry = $this->first('@string{acta = {From the file}} @article{a, j = acta, k = cacm}', $configure);

        $this->assertSame('From the file', $entry['j']);
        $this->assertSame('Communications', $entry['k']);
    }

    public function test_macro_names_are_case_insensitive(): void
    {
        $entry = $this->first('@string{Acta = {Acta Informatica}} @article{a, journal = ACTA}');

        $this->assertSame('Acta Informatica', $entry['journal']);
    }

    public function test_the_preamble_is_available_and_concatenated(): void
    {
        [$doc] = $this->parse('@preamble{ "\\newcommand{\\x}{y}" } @preamble{ " and more" } @article{a, t = {x}}');

        $this->assertSame('\\newcommand{\\x}{y} and more', $doc->preamble());
    }

    public function test_the_string_table_is_available(): void
    {
        [$doc] = $this->parse(
            '@string{acta = {Acta Informatica}}',
            static fn (BibtexParser $p) => $p->withStrings(['cacm' => 'Communications'])
        );

        $this->assertSame(['cacm' => 'Communications', 'acta' => 'Acta Informatica'], $doc->strings());
    }

    // --- what the old parser got wrong ---------------------------------------

    public function test_an_undefined_macro_keeps_its_token_and_is_reported(): void
    {
        [$doc] = $this->parse('@article{a, journal = nosuch}');

        $this->assertSame('nosuch', $doc->entries()[0]['journal']);
        $this->assertSame(['nosuch'], $doc->undefinedStrings());
        $this->assertStringContainsString('Undefined string', (string) $doc->diagnostics()[0]);
    }

    /**
     * The value of one field containing the name of another used to confuse the
     * old parser, which located values by searching the raw entry for them.
     */
    public function test_a_value_that_repeats_a_field_name_is_read_correctly(): void
    {
        $entry = $this->first('@article{a, author = {journal and title}, journal = {Real Journal}, title = {Real Title}}');

        $this->assertSame('journal and title', $entry['author']);
        $this->assertSame('Real Journal', $entry['journal']);
        $this->assertSame('Real Title', $entry['title']);
    }

    public function test_an_equals_sign_inside_a_value_is_not_a_field_boundary(): void
    {
        $entry = $this->first('@article{a, url = {https://x.test/?q=1&r=2}, title = {t}}');

        $this->assertSame('https://x.test/?q=1&r=2', $entry['url']);
        $this->assertSame('t', $entry['title']);
    }

    public function test_a_duplicate_field_keeps_the_last_and_says_so(): void
    {
        [$doc] = $this->parse('@article{a, title = {First}, title = {Second}}');

        $this->assertSame('Second', $doc->entries()[0]['title']);
        $this->assertStringContainsString('appears twice', (string) $doc->diagnostics()[0]);
    }

    public function test_a_truncated_entry_is_reported_rather_than_patched(): void
    {
        [$doc] = $this->parse('@article{a, title = {never closed');

        $this->assertTrue($doc->hasErrors());
        $this->assertSame(BibtexDiagnostic::ERROR, $doc->diagnostics()[0]->severity);
    }

    public function test_a_diagnostic_names_the_line_and_the_entry(): void
    {
        [$doc] = $this->parse("@article{a,\n  title = {ok},\n  journal = nosuch\n}");

        $this->assertSame(3, $doc->diagnostics()[0]->line);
        $this->assertSame('a', $doc->diagnostics()[0]->citation);
    }

    // --- the switches --------------------------------------------------------

    /**
     * Delimiters go either way - they are syntax - and only the substitution is
     * withheld. The old parser draws the line in the same place: its
     * removeDelimit is on whatever expandMacro says.
     */
    public function test_expansion_can_be_turned_off_leaving_the_macro_name(): void
    {
        $entry = $this->first(
            '@string{acta = {Acta}} @article{a, journal = acta # " I", title = {Braced}}',
            static fn (BibtexParser $p) => $p->expandStrings(false)
        );

        $this->assertSame('acta I', $entry['journal']);
        $this->assertSame('Braced', $entry['title']);
    }

    public function test_whitespace_in_a_value_is_contracted_by_default(): void
    {
        $entry = $this->first("@article{a, title = {Line one\n      and line two}}");

        $this->assertSame('Line one and line two', $entry['title']);
    }

    public function test_contraction_can_be_turned_off(): void
    {
        $entry = $this->first(
            "@article{a, title = {Line one\n      and line two}}",
            static fn (BibtexParser $p) => $p->contractWhitespace(false)
        );

        $this->assertSame("Line one\n      and line two", $entry['title']);
    }

    /**
     * The old parser reaches the same result by a different route:
     * extractEntries() rejoins the file line by line with a space, so a newline
     * is already a space before its /\s\s+/ regex sees it. Reading that
     * backwards is what the first run over a real volume caught.
     */
    public function test_legacy_mode_contracts_newlines_as_the_old_parser_does(): void
    {
        $entry = $this->first(
            "@article{a, title = {one\ntwo  three}}",
            static fn (BibtexParser $p) => $p->legacyMode()
        );

        $this->assertSame('one two three', $entry['title']);
    }

    /** removeDelimiters() empties the name only until it has recorded it once. */
    public function test_legacy_mode_empties_an_undefined_macro_only_the_first_time(): void
    {
        $entries = $this->entries(
            '@article{a, j = nosuch} @article{b, j = nosuch}',
            static fn (BibtexParser $p) => $p->legacyMode()
        );

        $this->assertSame('', $entries[0]['j']);
        $this->assertSame('nosuch', $entries[1]['j']);
    }

    public function test_legacy_mode_empties_an_undefined_macro(): void
    {
        $entry = $this->first('@article{a, journal = nosuch}', static fn (BibtexParser $p) => $p->legacyMode());

        $this->assertSame('', $entry['journal']);
    }

    /**
     * A macro written for concatenation carries its own spacing, and trimming
     * the definition silently welds the pieces together. Found by running the
     * parser over a real volume, not by reading the grammar.
     */
    public function test_a_string_keeps_the_spaces_it_was_defined_with(): void
    {
        $entry = $this->first(
            '@string{proc = "Proc.~of the "} @string{ip = " Int. Symp."}'
                .' @article{a, booktitle = proc # "27th" # ip}'
        );

        $this->assertSame('Proc.~of the 27th Int. Symp.', $entry['booktitle']);
    }

    /**
     * A value ending in a stray backslash before its closing brace. BibTeX
     * counts braces literally and closes the group; reading the backslash as an
     * escape loses every entry below it, which is how a 48-entry file came back
     * with one.
     */
    public function test_a_stray_backslash_before_a_closing_brace_still_closes_it(): void
    {
        $entries = $this->entries(
            '@book{a, author = {Dreveton, M.\\}, year = {2022}} @article{b, title = {Next}}'
        );

        $this->assertCount(2, $entries);
        $this->assertSame('Dreveton, M.\\', $entries[0]['author']);
        $this->assertSame('Next', $entries[1]['title']);
    }

    /** One entry whose braces do not add up must not cost the ones below it. */
    public function test_an_unclosed_entry_does_not_swallow_the_rest_of_the_file(): void
    {
        [$doc] = $this->parse('@article{broken, title = "never closed @article{good, title = {Fine}}');

        $citations = array_column($doc->entries(), 'bibtexCitation');

        $this->assertContains('good', $citations);
        $this->assertTrue($doc->hasErrors());
    }

    /**
     * One diagnostic per undefined name, not per use. A file the scanner loses
     * its place in can reference the same name thousands of times, and an
     * object for each is how a real volume exhausted a gigabyte.
     */
    public function test_an_undefined_macro_is_reported_once_however_often_it_is_used(): void
    {
        $entries = str_repeat('@article{a, j = nosuch, k = nosuch} ', 50);

        [$doc] = $this->parse($entries);

        $this->assertCount(1, $doc->diagnostics());
        $this->assertSame(['nosuch'], $doc->undefinedStrings());
    }

    /**
     * DOI-derived keys carry parentheses, and a name that stops at one takes
     * the whole entry's fields with it: this exact key cost 16 fields.
     */
    public function test_a_citation_key_may_contain_parentheses(): void
    {
        $entry = $this->first(
            '@article{10.1016/S0304-3975(00)00399-6, author = {Blondel, V.}, year = {2001}}'
        );

        $this->assertSame('10.1016/S0304-3975(00)00399-6', $entry['bibtexCitation']);
        $this->assertSame('Blondel, V.', $entry['author']);
        $this->assertSame('2001', $entry['year']);
    }

    /** A keyless entry still has to be recognised, spaces around "=" or not. */
    public function test_a_keyless_entry_is_recognised_without_spaces_around_the_equals(): void
    {
        $entry = $this->first('@book{author={Nobody}, title={Keyless}}');

        $this->assertSame('', $entry['bibtexCitation']);
        $this->assertSame('Nobody', $entry['author']);
    }

    /**
     * The old parser drops a field whose value is 0, because reduceFields()
     * tests the string for truthiness and "0" is falsy in PHP.
     */
    public function test_a_field_whose_value_is_zero_is_kept(): void
    {
        $entry = $this->first('@misc{a, title = 0, year = 2021}');

        $this->assertSame('0', $entry['title']);
        $this->assertSame('2021', $entry['year']);
    }
}
