<?php

namespace Dagstuhl\Latex\Tests\Bibtex;

use Dagstuhl\Latex\Bibliography\Parser\BibtexScanner;
use PHPUnit\Framework\TestCase;

class BibtexScannerTest extends TestCase
{
    private function scanner(string $source): BibtexScanner
    {
        return new BibtexScanner($source);
    }

    public function test_it_reads_a_flat_brace_group(): void
    {
        $this->assertSame('Hello', $this->scanner('{Hello} rest')->readBraceGroup());
    }

    public function test_it_reads_nested_brace_groups_whole(): void
    {
        $scanner = $this->scanner('{A {nested {deeply}} group} rest');

        $this->assertSame('A {nested {deeply}} group', $scanner->readBraceGroup());
        $this->assertSame(' rest', $scanner->slice($scanner->position(), 99));
    }

    /**
     * Braces are counted literally, backslashes and all, as BibTeX does. Paired
     * \{ and \} balance each other, so well-formed LaTeX comes out whole.
     */
    public function test_paired_escaped_braces_survive_literal_counting(): void
    {
        $this->assertSame('a \\{ b \\} c', $this->scanner('{a \\{ b \\} c}')->readBraceGroup());
    }

    /**
     * And an unpaired one closes the group, which is the point: a value ending
     * "M.\}" is a stray backslash that real .bib files contain, and treating it
     * as an escape would swallow every entry below it.
     */
    public function test_an_unpaired_escaped_brace_closes_the_group(): void
    {
        $this->assertSame('Dreveton, M.\\', $this->scanner('{Dreveton, M.\\}, year = {2022}')->readBraceGroup());
    }

    public function test_an_unclosed_group_is_reported_rather_than_guessed_at(): void
    {
        $this->assertNull($this->scanner('{never closed')->readBraceGroup());
    }

    public function test_it_reads_a_quoted_string(): void
    {
        $this->assertSame('Hello', $this->scanner('"Hello" rest')->readQuotedString());
    }

    /** The case no regular expression gets right: a quote inside a group. */
    public function test_a_quote_inside_braces_does_not_end_a_quoted_string(): void
    {
        $this->assertSame('A {"} B', $this->scanner('"A {"} B" rest')->readQuotedString());
    }

    public function test_braces_may_nest_inside_a_quoted_string(): void
    {
        $this->assertSame('A {b {c} d} e', $this->scanner('"A {b {c} d} e"')->readQuotedString());
    }

    public function test_an_unterminated_quoted_string_is_reported(): void
    {
        $this->assertNull($this->scanner('"never closed')->readQuotedString());
    }

    public function test_it_reads_an_entry_body_up_to_its_own_delimiter(): void
    {
        $scanner = $this->scanner('key, title = {Foo}} after');

        $this->assertSame('key, title = {Foo}', $scanner->readEntryBody('}'));
        $this->assertSame(' after', $scanner->slice($scanner->position(), 99));
    }

    /** A closing brace inside a value belongs to the value. */
    public function test_an_entry_body_ends_only_at_depth_zero(): void
    {
        $this->assertSame(
            'k, t = {a {b} c}, u = "d"',
            $this->scanner('k, t = {a {b} c}, u = "d"} after')->readEntryBody('}')
        );
    }

    /** Parenthesised entries exist, and a ) inside a value must not close them. */
    public function test_a_parenthesised_body_ignores_parentheses_in_values(): void
    {
        $this->assertSame(
            'k, t = {a ) b}, u = "c ) d"',
            $this->scanner('k, t = {a ) b}, u = "c ) d") after')->readEntryBody(')')
        );
    }

    public function test_a_truncated_entry_body_is_reported(): void
    {
        $this->assertNull($this->scanner('key, title = {Foo}')->readEntryBody('}'));
    }

    public function test_it_reads_names_up_to_what_ends_them(): void
    {
        $this->assertSame('article', $this->scanner('article{x')->readName());
        $this->assertSame('author', $this->scanner('author = {x}')->readName());
        $this->assertSame('smith:2020', $this->scanner('smith:2020, x')->readName());
        $this->assertSame('jan', $this->scanner('jan # " 2020"')->readName());
    }

    public function test_it_skips_whitespace_and_accepts_characters(): void
    {
        $scanner = $this->scanner("   \n\t = x");

        $scanner->skipWhitespace();

        $this->assertTrue($scanner->accept('='));
        $this->assertFalse($scanner->accept('='));
    }

    public function test_it_reports_the_line_of_a_position(): void
    {
        $scanner = $this->scanner("one\ntwo\nthree");

        $scanner->skipTo('h');

        $this->assertSame(3, $scanner->line());
    }

    public function test_skipping_to_a_character_that_is_not_there_lands_at_the_end(): void
    {
        $scanner = $this->scanner('no at sign here');

        $this->assertFalse($scanner->skipTo('@'));
        $this->assertTrue($scanner->atEnd());
    }
}
