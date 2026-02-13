<?php

namespace Dagstuhl\Latex\LatexStructures;

use Dagstuhl\Latex\Strings\Converter;
use Dagstuhl\Latex\Strings\MathString;
use Dagstuhl\Latex\Strings\MetadataString;
use Dagstuhl\Latex\Utilities\PlaceholderManager;

class LatexMacro extends LatexString
{
    const MACROS_1_ARG = [
        'textbf', 'bibitem',
        'section', 'section*',
        'subsection', 'subsection*',
        'subsubsection', 'subsubsection*',
        'paragraph', 'paragraph*',
        'subparagraph', 'subparagraph*',
        'dutchPrefix', 'noopsort'
    ];

    const REPLACEMENT_LEFT_CURLY_BRACKET = '__LeFt__CuRlY__bRaCkEt__';
    const REPLACEMENT_RIGHT_CURLY_BRACKET = '__RiGhT__CuRlY__bRaCkEt__';

    private string $name;
    private array $options;
    private array $arguments;
    private string $snippet;

    private array $linesToPrepend = [];
    private array $linesToAppend = [];

    public array $_log = [];

    private static function parseOptions(string $optionsString): array
    {
        $height = 0;

        $splitAt = [ 0 ];

        for ($pos = 0; $pos < strlen($optionsString); $pos++) {

            $char = substr($optionsString, $pos, 1);

            if ($char === '}' AND $height > 0) {
                $height--;
            }
            elseif ($char === '{') {
                $height++;
            }

            if ($height === 0 AND $char === ',') {
                $splitAt[] = $pos;
            }
        }

        $options = [];

        foreach ($splitAt as $key=>$startPos) {
            $endPos = $splitAt[$key+1] ?? strlen($optionsString);

            $option = trim(substr($optionsString, $startPos, $endPos - $startPos));

            $option = ltrim($option, ' ,');
            $option = rtrim($option, ' ,');

            if ($option != '') {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * macro syntax: \name[option_1,option_2,...,option_k]{arg_1}{arg_2}...{arg_n}
     * @return static[]
     */
    public static function _getMacros(string $name, string $string, ?LatexFile $latexFile = NULL): array
    {
        $regEx = str_replace('@@NAME@@', preg_quote($name), LatexPatterns::MACRO_BEGIN);

        $matches = [];
        $macroData = [];

        if (preg_match_all($regEx, $string, $matches, PREG_OFFSET_CAPTURE) !== false) {

            // parse options
            foreach($matches[1] as $key=>$match) {

                if ($match === '') {
                    $options = [];
                }
                else {
                    $optionsString = $match[0] ?? '';
                    $optionsString = trim(preg_replace('/\[|\]/', '', $optionsString));
                    $options = self::parseOptions($optionsString);
                }

                $macroData[$key]['options'] = $options;
            }

            // parse arguments
            foreach($matches[0] as $key=>$match) {
                $macroStartsAt = $match[1];
                $argStartsAt = strlen($match[0]) + $macroStartsAt;
                $argEndsAt = NULL;  // will be evaluated by getMacroArgs

                $macroData[$key]['arguments'] = self::getMacroArgs($string, $argStartsAt, $argEndsAt, $name);
                $macroData[$key]['snippet'] = trim(substr($string, $macroStartsAt, $argEndsAt-$macroStartsAt+1));
                $macroData[$key]['latexFile'] = $latexFile;
            }
        }

        $macros = [];

        foreach($macroData as $macro) {

            $macros[] = new static([
                'name' => $name,
                'options' => $macro['options'],
                'arguments' => $macro['arguments'],
                'snippet' => $macro['snippet'],
                'latexFile' => $macro['latexFile']
            ]);
        }

        return $macros;
    }

    /**
     * @return string[]
     */
    public static function getMacroArgs(string $string, int $offset, int &$argEndsAt = NULL, string $name = NULL): array
    {
        $value = '';
        $height = 1;

        $string = substr($string, $offset);

        $string = preg_replace('/([^\\\\])\\\\{/', '$1'.self::REPLACEMENT_LEFT_CURLY_BRACKET, $string);
        $string = preg_replace('/([^\\\\])\\\\{/', '$1'.self::REPLACEMENT_LEFT_CURLY_BRACKET, $string);
        $string = preg_replace('/([^\\\\])\\\\\}/', '$1'.self::REPLACEMENT_RIGHT_CURLY_BRACKET, $string);
        $string = preg_replace('/([^\\\\])\\\\\}/', '$1'.self::REPLACEMENT_RIGHT_CURLY_BRACKET, $string);

        $pos = 0;
        $nextChar = substr($string, $pos, 1);
        $stringLength = strlen($string);

        while($nextChar === '{' OR $nextChar === "\n" OR $nextChar === ' ' OR ($height > 0 AND $pos < $stringLength)) {

            $char = $nextChar;

            if ($char === '}') {
                $height--;
            }
            elseif ($char === '{') {
                $height++;
            }

            if ($height > 0) {
                $value .= $char;
            }

            $pos = $pos + 1;

            if ($pos < $stringLength) {
                $nextChar = substr($string, $pos, 1);

                if ($nextChar === '{' AND $height === 0) {
                    $value .= '}#-#-#';
                }
            }
            elseif ($pos > $stringLength) {
                break;
            }

            if ($height === 0 AND $name !== NULL AND in_array($name, self::MACROS_1_ARG)) {
                break;
            }
        }

        $argEndsAt = $offset + $pos - 1;

        $correctionLeft = strlen(self::REPLACEMENT_LEFT_CURLY_BRACKET) - strlen('\{');
        $argEndsAt -= substr_count($value, self::REPLACEMENT_LEFT_CURLY_BRACKET) * $correctionLeft;
        $correctionRight = strlen(self::REPLACEMENT_RIGHT_CURLY_BRACKET) - strlen('\}');
        $argEndsAt -= substr_count($value,self::REPLACEMENT_RIGHT_CURLY_BRACKET) * $correctionRight;

        $value = str_replace(self::REPLACEMENT_LEFT_CURLY_BRACKET, '\{', $value);
        $value = str_replace(self::REPLACEMENT_RIGHT_CURLY_BRACKET, '\}',$value);

        return explode('}#-#-#{',$value);
    }

    public function __construct($attributes)
    {
        $this->name = $attributes['name'];
        $this->options = $attributes['options'];
        $this->arguments = $attributes['arguments'];
        $this->snippet = $attributes['snippet'];

        $string = '{'.implode('}{', $this->arguments).'}';

        parent::__construct($string, $attributes['latexFile']);
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function getMacroString($argument, $comment = ''): string
    {
        if ($comment !== '') {
            $comment .= "\n";
        }

        return $comment.'\\'.$this->name.'{'.$argument.'}';
    }

    /**
     * @return string[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return string[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    // NOTE: getArgument and setArgument should actually be used only for macros with one argument

    /**
     * @return string
     */
    public function getArgument(): string
    {
        return $this->arguments[0] ?? '';
    }

    public function setArgument(string $newValue): void
    {
        if (isset($this->arguments[0])) {
            $this->arguments[0] = $newValue;
        }
    }

    public function setOptions(array $newOptions): void
    {
        $this->options = $newOptions;
    }

    public function setArguments(array $newArguments): void
    {
        $this->arguments = $newArguments;
    }

    public function hasOption(string $name): bool
    {
        return in_array($name, $this->getOptions());
    }

    public function getSnippet(bool $generateNew = false): string
    {
        if (!$generateNew) {
            return $this->snippet;
        }
        else {
            $options = implode(',', $this->getOptions());
            $arguments = '{' . implode('}{', $this->getArguments()) . '}';

            $newSnippet = '\\' . $this->getName();

            if (strlen($options) > 0) {
                $newSnippet .= '[' . $options . ']';
            }

            $newSnippet .= $arguments;

            return $newSnippet;
        }
    }

    public function contains(string $string): bool
    {
        return substr_count($this->getSnippet(), $string) > 0;
    }

    public function writeToLatexFile(string $newSnippet = NULL, string $snippetToBeReplaced = NULL, bool $addSpace = false): void
    {
        $file = $this->getLatexFile();

        if ($file === NULL) {
            return;
        }

        if ($snippetToBeReplaced === NULL) {
            $snippetToBeReplaced = $this->getSnippet();
        }

        if ($newSnippet === NULL) {
            $newSnippet = $this->getSnippet(true);
        }

        $linesToPrepend = implode("\n", $this->linesToPrepend);
        $linesToAppend = implode("\n", $this->linesToAppend);

        if ($linesToPrepend !== '') {
            $linesToPrepend .= "\n";
        }

        if ($linesToAppend !== '') {
            $linesToAppend = "\n".$linesToAppend;
        }

        $newSnippet = $linesToPrepend.$newSnippet.$linesToAppend;

        $contents = $file->getContents();

        if ($newSnippet === '' AND $addSpace) {
            $contents = str_replace($snippetToBeReplaced, ' {}', $contents);
        }
        else {
            $contents = str_replace($snippetToBeReplaced, $newSnippet, $contents);
        }

        $this->hasValidParseTreeCache = false;
        $this->hasValidCommentFreeCache = false;
        $this->snippet = $newSnippet;   // TODO: Does this have side effects?

        $file->setContents($contents);
    }

    public function prepend(string $line): void
    {
        $this->linesToPrepend[] = $line;
    }

    public function append(string $line): void
    {
        $this->linesToAppend[] = $line;
    }

    public function getTexOrPdfStringVersion(bool $boldMath = true, bool $fullMacro = true): string
    {
        $argument = $this->getArgument();

        $oldArgument = $argument;

        if (str_contains($argument, 'texorpdfstring')) {

            if ($boldMath) {

                $argumentBold = $argument;

                $texOrPdfStrings = $this->getMacros('texorpdfstring');

                foreach($texOrPdfStrings as $string) {
                    $texString = $string->getArguments()[0];

                    if (str_contains($texString, '$') AND !str_contains($texString, '\boldmath')) {
                        $boldTexString = '\boldmath '.$texString;
                        $argumentBold = str_replace($texString, $boldTexString, $argumentBold);
                    }
                }

                $comment = '%'.$this->getMacroString($argumentBold)."\n";
            }
            else {
                $comment = '%'.$this->getMacroString($argument)."\n";
            }
        }

        else {
            $mathMgr = new PlaceholderManager();

            $argument = $mathMgr->substitutePatterns([ '/\$(.*)\$/U' ], $argument);

            $mathSnippets = $mathMgr->getSnippets();

            foreach($mathSnippets as $key=>$mathSnippet) {

                $latexSnippet = new LatexString($mathSnippet);

                // remove \bm{...} from $mathSnippet since \boldmath is added at the end
                foreach($latexSnippet->getMacros('bm') as $bm) {
                    $mathSnippet = str_replace($bm->getSnippet(), $bm->getArgument(), $mathSnippet);
                }

                $mathString = new MetadataString($mathSnippet, $this->getLatexFile());
                $mathString = $mathString->expandMacros()->getString();

                $mathString = str_replace('\tilde{\mathcal{O}}', 'O~', $mathString);

                $mathString = new MathString($mathString);
                $asciiString = $mathString->convertToText(Converter::MAP_LATEX_TO_ASCII,true);

                $asciiString = str_replace('\\infty', 'infinity', $asciiString->getString());
                $asciiString = str_replace('\\overline', 'overline', $asciiString);

                // add \ before special characters
                // all characters not specified here will be quoted
                $asciiString = preg_replace("#([^A-Za-z+\-*\/0-9\\\\ \!\:\|\.\,\;\=\(\)\>\<\[\]\']{1,1})#", '12345678987654321$1', $asciiString);

                $asciiString = str_replace('12345678987654321', "\\", $asciiString);
                $asciiString = str_replace('\{\}\\^*', '*', $asciiString);
                $asciiString = str_replace('\\^*', '*', $asciiString);
                $asciiString = str_replace('\\^', '\textasciicircum ', $asciiString);
                $asciiString = str_replace(' \\circ ', ' o ', $asciiString);
                $asciiString = str_replace('~', '\textasciitilde ', $asciiString);

                $asciiString = str_replace('\\\\textasciitilde', '\textasciitilde', $asciiString);

                foreach([ 'overleftarrow', 'varepsilon', 'sum', 'oplus', 'cup' ] as $reservedWord) {
                    $asciiString = str_replace('\\'.$reservedWord, $reservedWord, $asciiString);
                }

                $asciiString = trim($asciiString);

                $mathSnippets[$key] = $boldMath
                    ? '\\texorpdfstring{\boldmath ' . $mathSnippet . '}{' . $asciiString . '}'
                    : '\\texorpdfstring{' . $mathSnippet . '}{' . $asciiString . '}';
            }

            $mathMgr->setSnippets($mathSnippets);
            $argument = $mathMgr->reSubstitute($argument);

            $comment = '%'.$this->getMacroString($argument)."\n";
            $argument = $oldArgument;
        }

        return $fullMacro
            ? $this->getMacroString($argument, $comment)
            : $argument.$comment;

    }
}