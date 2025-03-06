<?php

namespace Dagstuhl\Latex\Strings;

use Dagstuhl\Latex\LatexStructures\LatexString;

class MathString extends MetadataString
{
    const TEX_WIDE_HAT = '/\\\\widehat{([a-zA-Z]+)}/';
    const ASCII_REPLACEMENT_WIDE_HAT = '$1^';
    const TEX_HAT = '/\\\\hat{([a-zA-Z])}/';
    const ASCII_REPLACEMENT_HAT = '$1^';

    const COMBINING_TILDE = '̃';        // DO NOT TOUCH! - $char . self::COMBINING_TILDE places tilde above $char
    const COMBINING_CIRCUMFLEX = '̂';   // DO NOT TOUCH! - $char . self::COMBINING_CIRCUMFLEX places circumflex above $char

    const SUB_AND_SUPERSCRIPTS = [
        '/\_0$/' => '₀',
        '/\_1$/' => '₁',
        '/\_2$/' => '₂',
        '/\_3$/' => '₃',
        '/\_4$/' => '₄',
        '/\_5$/' => '₅',
        '/\_6$/' => '₆',
        '/\_7$/' => '₇',
        '/\_8$/' => '₈',
        '/\_9$/' => '₉',

        '/\_0([^0-9])/' => '₀$1',
        '/\_1([^0-9])/' => '₁$1',
        '/\_2([^0-9])/' => '₂$1',
        '/\_3([^0-9])/' => '₃$1',
        '/\_4([^0-9])/' => '₄$1',
        '/\_5([^0-9])/' => '₅$1',
        '/\_6([^0-9])/' => '₆$1',
        '/\_7([^0-9])/' => '₇$1',
        '/\_8([^0-9])/' => '₈$1',
        '/\_9([^0-9])/' => '₉$1',
        '/\_\{0\}/' => '₀',
        '/\_\{1\}/' => '₁',
        '/\_\{2\}/' => '₂',
        '/\_\{3\}/' => '₃',
        '/\_\{4\}/' => '₄',
        '/\_\{5\}/' => '₅',
        '/\_\{6\}/' => '₆',
        '/\_\{7\}/' => '₇',
        '/\_\{8\}/' => '₈',
        '/\_\{9\}/' => '₉',

        '/\^i$/' => 'ⁱ',
        '/\^n$/' => 'ⁿ',
        '/\^1$/' => '¹',
        '/\^2$/' => '²',
        '/\^3$/' => '³',
        '/\^0$/' => '⁰',
        '/\^4$/' => '⁴',
        '/\^5$/' => '⁵',
        '/\^6$/' => '⁶',
        '/\^7$/' => '⁷',
        '/\^8$/' => '⁸',
        '/\^9$/' => '⁹',
        '/\^i([^0-9A-Za-z])/' => 'ⁱ$1',
        '/\^n([^0-9A-Za-z])/' => 'ⁿ$1',
        '/\^1([^0-9])/' => '¹$1',
        '/\^2([^0-9])/' => '²$1',
        '/\^3([^0-9])/' => '³$1',
        '/\^0([^0-9])/' => '⁰$1',
        '/\^4([^0-9])/' => '⁴$1',
        '/\^5([^0-9])/' => '⁵$1',
        '/\^6([^0-9])/' => '⁶$1',
        '/\^7([^0-9])/' => '⁷$1',
        '/\^8([^0-9])/' => '⁸$1',
        '/\^9([^0-9])/' => '⁹$1',
        '/\^\{i\}/' => 'ⁱ',
        '/\^\{n\}/' => 'ⁿ',
        '/\^\{1\}/' => '¹',
        '/\^\{2\}/' => '²',
        '/\^\{3\}/' => '³',
        '/\^\{0\}/' => '⁰',
        '/\^\{4\}/' => '⁴',
        '/\^\{5\}/' => '⁵',
        '/\^\{6\}/' => '⁶',
        '/\^\{7\}/' => '⁷',
        '/\^\{8\}/' => '⁸',
        '/\^\{9\}/' => '⁹',

    ];

    /**
     * @return $this
     *
     * extract contents from \[...\] and $...$
     */
    public function stripMathDelimiters(): static
    {
        $string = $this->string;
        $string = trim($string);

        $displayMath = MetadataString::NEWLINE_PLACEHOLDER.'$1'.MetadataString::NEWLINE_PLACEHOLDER;

        $string = preg_replace('/^\\\\\[(.*)\\\\]$/sm', '$1', $string);
        $string = preg_replace('/^\$(.*)\$$/sm', '$1', $string);
        $string = preg_replace('/^\\\\begin{equation}(.*)\\\\end{equation}$/sm', $displayMath, $string);
        $string = preg_replace('/^\\\\begin{align}(.*)\\\\end{align}$/sm', $displayMath, $string);
        $string = preg_replace('/^\\\\begin{equation\*}(.*)\\\\end{equation\*}$/sm', $displayMath, $string);
        $string = preg_replace('/^\\\\begin{align\*}(.*)\\\\end{align\*}$/sm', $displayMath, $string);
        $this->string = $string;

        return $this;
    }

    public function convertToText(string $charMapName, bool $stripMathDelimiters = true): static
    {
        $this->dropPdfStrings()
            ->replaceXspace()
            ->convertFracToAscii()
            ->convertTildeToAscii($charMapName);

        if (str_contains($this->string, '\card{')) {
            $latexString = new LatexString($this->string);

            foreach($latexString->getMacros('card') as $cardMacro) {
                $this->string = str_replace($cardMacro->getSnippet(), '|'.$cardMacro->getArgument().'|', $this->string);
            }
        }

        if ($charMapName === Converter::MAP_LATEX_MATH_TO_UTF8) {
            $this->replaceSubAndSuperscripts();
        }

        $this->hotFixPreConvertCorrections();

        $this->convertMathSymbols($charMapName);

        // remove font macros carefully
        // the intention of this replacement is to prevent e.g. \mathrm{{polylog}\;}{n} to be considered as one macro -> {n} would be removed
        // see ITCS 2020 p010
        $this->string = str_replace('}{', '} {', $this->string);
        $this->removeFontMacros();
        $this->string = preg_replace('/(\\\\frac\{.*\}) (\{.*\})/U', '$1$2', $this->string);
        $this->string = preg_replace('/\\\\binom\{(.*)\} \{(.*)\}/U', 'binom($1,$2)', $this->string);

        $this->reviseOperatorName()
            ->convertFracToAscii()
            ->replaceTexBlankSpacesByBlank();

        $this->improveSpacing();

        $this->addBlanksBeforeMacros()
            ->reduceBlanks()
            ->trimBrackets();

        $this->convertCircToAscii()
            ->convertHatToAscii($charMapName)
            ->reviseEscapedAsciiChars();

        if ($stripMathDelimiters) {
            $this->stripMathDelimiters();
        }

        $this->hotFixPostConvertCorrections();

        $this->reduceBlanks()
            ->trim();

        return $this;
    }

    private function improveSpacing(): static
    {
        $string = $this->string;

        $relations = [
            '=', '∈', '∉', '→', '', '⊂', '⊃', '⊄', '⊅', '⊆', '⊇', '⊈', '⊉', '<', '>', '⋐', '⋑', '⩽', '⩾', '≤', '≥',
            '⊎',
        ];

        foreach ($relations as $relation) {
            $string = str_replace($relation, ' '.$relation.' ', $string);
        }

        $string = preg_replace('/\h*∘\h*/u', '∘', $string);

        $this->string = $string;

        return $this;
    }

    private function hotFixPreConvertCorrections(): static
    {
        $this->string = str_replace('{\mathcal T}', '\mathcal{T}', $this->string);

        return $this;
    }

    // TODO: add hot-fixes here, try to handle systematically if patterns are recognised
    private function hotFixPostConvertCorrections(): static
    {
        $this->string = str_replace('Õ (', 'Õ(', $this->string);
        $this->string = str_replace('\left(', '(', $this->string);
        $this->string = preg_replace('/\h*\\\\right\)/', ')', $this->string);
        $this->string = preg_replace('/\√\{(.{1,1})\}/U', '√$1', $this->string);

        $this->string = str_replace ('\|', '‖', $this->string);

        return $this;
    }

    public function addBlanksBeforeMacros(): static
    {
        $this->string = preg_replace('/([A-Za-z0-9])(\\\\)/', '$1 $2', $this->string); // CAUTION: \right\{ -> \right \{

        return $this;
    }

    /**
     * @return $this
     *
     * move whitespaces after opening or before closing bracket
     */
    public function trimBrackets(): static
    {
        $this->reduceBlanks();

        $string = $this->string;

        $string = str_replace('( ', '(', $string);
        $string = str_replace(' )', ')', $string);

        $string = str_replace(' }', '}', $string);
        $string = str_replace('{ ', '{', $string);

        $string = str_replace('[ ', '[', $string);
        $string = str_replace(' ]', ']', $string);

        $this->string = $string;

        return $this;
    }

    public function convertTildeToAscii(string $charMapName = Converter::MAP_LATEX_TO_UTF8): static
    {
        $latexString = $this->toLatexString();

        if ($charMapName === Converter::MAP_LATEX_TO_ASCII) {
            foreach ($latexString->getMacros('tilde') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument().'~', $this->string);
            }

            foreach ($latexString->getMacros('widetilde') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument().'~', $this->string);
            }
        }
        else {
            foreach ($latexString->getMacros('tilde') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument() . self::COMBINING_TILDE, $this->string);
            }

            foreach ($latexString->getMacros('widetilde') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument() . self::COMBINING_TILDE, $this->string);
            }
        }

        return $this;
    }

    public function convertFracToAscii(): static
    {
        $string = preg_replace('/\\\\frac([0-9]{1})\{([0-9]+)\}/', '$1/$2', $this->string);
        $string = preg_replace('/\\\\frac\{([0-9]+)\}\{([0-9]+)\}/', '$1/$2', $string);
        $string = preg_replace('/\\\\frac\{(.{1})\}\{(.{1})\}/U', '$1/$2', $string);
        $string = preg_replace('/\\\\frac\{(.{1})\}\{(.{2,6})\}/U', '$1/($2)', $string);

        $string = preg_replace('/\\\\nicefrac\{([0-9]+)\}\{([0-9]+)\}/U', '$1/$2', $string);
        $string = preg_replace('/\\\\nicefrac\{(.{1,8})\}\{(.{1,8})\}/U', '($1)/($2)', $string);

        $this->string = str_replace('\nicefrac1\eps', '1/epsilon', $string);

        return $this;
    }

    public function convertCircToAscii(): static
    {
        $string = str_replace('^{°}', '°', $this->string);
        $this->string = str_replace('^°', '°', $string);

        return $this;
    }

    public function convertHatToAscii(string $charMapName = Converter::MAP_LATEX_TO_UTF8): static
    {
        $latexString = $this->toLatexString();

        if ($charMapName === Converter::MAP_LATEX_TO_ASCII) {
            foreach ($latexString->getMacros('hat') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument().'^' , $this->string);
            }

            foreach ($latexString->getMacros('widehat') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument().'^', $this->string);
            }
        }
        else {
            foreach ($latexString->getMacros('hat') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument() . self::COMBINING_CIRCUMFLEX, $this->string);
            }

            foreach ($latexString->getMacros('widehat') as $macro) {
                $this->string = str_replace($macro->getSnippet(), $macro->getArgument() . self::COMBINING_CIRCUMFLEX, $this->string);
            }
        }

        return $this;
    }

    private function reviseEscapedAsciiChars(): static
    {
        // safely, i.e. only when a blank follows
        $this->string = preg_replace('#\\\\([A-Za-z])(=|\_|\^|\)|\?|}|\.|\:|\s|$)#', '$1$2', $this->string);

        return $this;
    }

    private function replaceSubAndSuperscripts(): static
    {
        foreach(self::SUB_AND_SUPERSCRIPTS as $regex=>$replacement) {
            $this->string = preg_replace($regex, $replacement, $this->string);
        }

        return $this;
    }
}