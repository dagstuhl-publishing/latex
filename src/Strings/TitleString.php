<?php

namespace Dagstuhl\Latex\Strings;

use Dagstuhl\Latex\LatexStructures\LatexString;
use Dagstuhl\Latex\Utilities\PlaceholderManager;

class TitleString
{
    // ------ Links -------

    // CapitalizeMyTitle:  https://capitalizemytitle.com
    // TitleCaseConverter: https://titlecaseconverter.com/


    private string $title;

    const NEWLINE_PATTERN = '\texorpdfstring{\\\\}{ }';
    const NEWLINE_REPLACEMENT = ' NEWLINE_NEWLINE_NEWLINE ';
    const TILDE_REPLACEMENT = ' TILDE_NON_BREAKING_SPACE_TILDE ';
    const FORMULA_PREFIX = 'XXXXXXXXXXX';
    const FORMULA_PLACEHOLDER = self::FORMULA_PREFIX.'@@INDEX@@'.self::FORMULA_PREFIX;

    /** LIST IS FILTERED BY LENGTH!
     *  by default: only use words with <= 4 letters from the list
     */
    const LOWER_CASE_EXCEPTIONS = [ 'a', 'about', 'above', 'across', 'after', 'against', 'along', 'among','an','and', 'around',
        'as', 'at', 'before', 'behind', 'below', 'beneath', 'beside','between', 'beyond', 'but', 'by', 'despite', 'down',
        'during', 'for', 'from', 'in', 'inside', 'into', 'near','nor', 'of', 'off', 'on','or', 'out','outside',
        'except', 'like', 'onto','past', 'per', 'since', 'so', 'the', 'through','throughout', 'till', 'to','toward','under',
        'underneath', 'until','up', 'upon','via', 'vs', 'vs.', 'vs.\\', 'with', 'within','without','over','yet'
    ];


    const NEVER_TOUCH = [ 'pClay', 'l_p', 'l_1', 'l_2', ' - A ', 'mu-Calculus'];

    const RESERVED_NAMES = [ 'de Bruijn', '' ];


    public function __construct(?string $title = '')
    {
        $title = StringHelper::replaceLinebreakByBlank($title);
        $title = StringHelper::replaceMultipleWhitespacesByOneBlank($title);

        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string[]
     */
    public function getWords(): array
    {
        return explode(' ', $this->getTitle());
    }

    public static function isLowerCaseException(string $word, int $length): bool
    {
        $newList = [];

        foreach (self::LOWER_CASE_EXCEPTIONS as $lcException) {
            if (strlen($lcException) <= $length ) {

                $newList [] = $lcException;
            }
        }
        $wordLc = lcfirst($word);

        //var_dump($newList);

        return in_array($wordLc ,$newList);
    }

    public static function isSpecialWord(string $word): bool
    {
        $count = 0;

        for ($i=0; $i < strlen($word); $i++) {

            if(ctype_upper($word[$i])) {

                $count++;
            }
        }

        if ($count > 1) {
            $result = true;
        }

        else {
            $result = false;
        }

        return $result;
    }

    public static function isOneLetterInFormula(string $word): bool
    {
        $result = false;

        if (strlen($word) === 1 AND $word !== 'a' ) {
            $result = true;
        }

        return $result;
    }

    public static function capitalizeCompositeWord(string $word): string
    {
        $partsNew = [];

        $parts = explode('-', $word);

        foreach ($parts as $part) {

            // example: d-SAT should remain as it is
            if (self::isSpecialWord($part) OR strlen($part) === 1){
                $partsNew[] = $part;
                // var_dump('formula: '.$part);
            }

            else {
                $partsNew[] = ucfirst($part);
            }
        }

        return implode('-', $partsNew);
    }

    public function capitalize(int $length = 4, string $title = NULL): string
    {
        if ($title === NULL) {
            $title = $this->title;
        }

        foreach (self::RESERVED_NAMES as $reservedName) {

            if(StringHelper::startsWith($title, $reservedName)) {
                $title = ucfirst($title);
            }
        }

        // ---- replace tilde as non-breaking space

        $title = str_replace(self::NEWLINE_PATTERN, self::NEWLINE_REPLACEMENT, $title);
        $title = preg_replace('/([^\\\\]{1})\~/', '$1'.self::TILDE_REPLACEMENT, $title);

        // ---- replace footnotes

        $footnoteManager = new PlaceholderManager(self::FORMULA_PLACEHOLDER);
        $latexString = new LatexString($title);
        $footnotes = $latexString->getMacros('footnote');

        $footnoteSnippets = [];
        foreach($footnotes as $footnote) {
            $footnoteSnippets[] = PlaceholderManager::getQuotedPattern($footnote->getSnippet());
        }

        $title = $footnoteManager->substitutePatterns($footnoteSnippets, $title);

        // ---- replace never touch words ----

        $neverTouchMgr = new PlaceholderManager(self::FORMULA_PLACEHOLDER);
        $neverTouch = [];
        foreach(self::NEVER_TOUCH as $word) {
            $neverTouch[] = '/\b'.preg_quote($word).'/';
        }
        $title = $neverTouchMgr->substitutePatterns($neverTouch, $title);


        // ---- replace reserved names ----

        $reservedNamesMgr = new PlaceholderManager(self::FORMULA_PLACEHOLDER);
        $reservedNames = [];
        foreach(self::RESERVED_NAMES as $word) {
            $reservedNames[] = '/'.preg_quote($word).'/';
        }
        $title = $reservedNamesMgr->substitutePatterns($reservedNames, $title);

        // ---- replace brackets ----

        $formulaMgr = new PlaceholderManager(self::FORMULA_PLACEHOLDER);
        $title = $formulaMgr->substitutePatterns([ '/\$(.*)\$/U' ], $title);


        // ---- replace formulas ----

        $bracketMgr = new PlaceholderManager(self::FORMULA_PLACEHOLDER);
        $title = $bracketMgr->substitutePatterns([ '/\((.*)\)/' ], $title);

        $brackets = $bracketMgr->getSnippets();

        foreach($brackets as $bracketNo=>$bracket) {

            // remove brackets at the beginning and the end
            $bracket = substr($bracket, 1, strlen($bracket)-2);

            $ucContent = $this->capitalize($length, $bracket);

            $brackets[$bracketNo] = '('.$ucContent.')';
        }

        $bracketMgr->setSnippets($brackets);

        $words = explode(' ',$title);

        $wordsNew = [];

        $followsColon = false;

        foreach ($words as $no=>$word) {

            if ($followsColon AND !self::isSpecialWord($word) AND !self::isOneLetterInFormula($word)) {
                $word = ucfirst($word);
            }

            if (self::isOneLetterInFormula($word)) {
                $newWord = $word;
            }

            elseif (str_contains($word, '-')) {
                //var_dump('composite word', $word);
                $newWord = self::capitalizeCompositeWord($word);
            }

            elseif (!self::isSpecialWord($word) AND ($no === 0 OR ($no === count($words) - 1))) {
                //var_dump('first or last');
                $newWord = ucfirst($word);
            }

            elseif (self::isSpecialWord($word)) {
                //var_dump('special word');
                $newWord = $word;
            }

            elseif (self::isLowerCaseException($word, $length) AND !$followsColon) {
                //var_dump('lowercase exception');
                $newWord = lcfirst($word);
            }

            else {
                //var_dump('other');
                $newWord = ucfirst($word);
            }

            $followsColon = StringHelper::endsWith($word, ':');

            // var_dump($newWord); echo "\n\n";

            $wordsNew[] = $newWord;
        }

        $capitalizedText = implode(' ',$wordsNew);

        $capitalizedText = str_replace(self::TILDE_REPLACEMENT, '~', $capitalizedText);
        $capitalizedText = str_replace(self::NEWLINE_PATTERN, self::NEWLINE_REPLACEMENT, $capitalizedText);

        $capitalizedTextWithBrackets = $bracketMgr->reSubstitute($capitalizedText);
        $capTextWithFormulaAndBrackets = $formulaMgr->reSubstitute($capitalizedTextWithBrackets);
        $capTextWithNeverTouchWords = $neverTouchMgr->reSubstitute($capTextWithFormulaAndBrackets);
        $capTextWithFootnotes = $footnoteManager->reSubstitute($capTextWithNeverTouchWords);

        return $reservedNamesMgr->reSubstitute($capTextWithFootnotes);
    }

}