<?php

namespace Dagstuhl\Latex\Strings;

use Dagstuhl\Latex\LatexStructures\LatexCommand;
use Dagstuhl\Latex\LatexStructures\LatexEnvironment;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\LatexStructures\LatexMacro;
use Dagstuhl\Latex\LatexStructures\LatexString;
use Dagstuhl\Latex\Utilities\PlaceholderManager;

class MetadataString
{
    const TEX_LABEL = '/\\\\label\{.*\}/U';
    const TEX_LINE_BREAKS_PATTERN = '#\\\linebreak|\\\\newline|\\\\\\\\(\[.{0,6}\]){0,1}#';
    const TEX_WORD_SEPARATOR_PATTERN = '#\\\-#';

    const TEX_BLANK_SPACES = '#\\\\ |\\\\,|\\\\;|\\\\quad|\\\\qquad|\\\\/|\\\\@#';

    const TEX_XSPACE_PUNCTUATION = '/\\\\xspace([\.\;\?\!\, ])/';
    const REPLACEMENT_XSPACE_PUNCTUATION = '$1';

    const TEX_XSPACE = '/\\\\xspace/';
    const REPLACEMENT_XSPACE = '';

    const TEX_FONT_COMMANDS = [
        'switch' =>  [ 'normalfont', 'em',   'rmfamily', 'sffamily', 'ttfamily', 'tt',     'upshape', 'itshape', 'it',     'slshape', 'scshape', 'sc',     'bfseries', 'mdseries', 'lfseries', 'mathsf', 'sf',     'cal',     'mathbb', 'bm',       'rm',      'bf',    'sl' ],
        'command' => [ 'textnormal', 'emph', 'textrm',   'textsf',   'texttt',   'texttt', 'textup',  'textit',  'textit', 'textsl',  'textsc',  'textsc', 'textbf',   'textmd',   'textlf',   'mathsf', 'textsf', 'mathcal', 'mathbb', 'boldmath', 'textrm', 'textbf', 'textit' ]
    ];

    const TEX_FORMAT_MACROS = [
        'boldsymbol', 'boldmath', 'bm',
        'underline',
        'textbf', 'textup', 'textsf', 'textsc', 'text',
        'mathsf', 'mathbf',
        'mathrm', 'mbox',
        'mathcal', 'mathbb',
        'mathit',
        'mathscr',
        'mathcal',
        'hbox',
        'cal',
        'rm'
    ];

    const ENUMERATION_ENVIRONMENTS = [ 'enumerate', 'bracketenumerate', 'romanenumerate', 'alphaenumerate', 'itemize' ];

    const ENUM_STYLE_ROMAN = 'enum-roman';
    const ENUM_STYLE_ARABIC = 'enum-arabic';
    const ENUM_STYLE_ALPHA = 'enum-alpha';
    const ENUM_STYLE_NONE = 'enum-none';

    const NEWLINE_PLACEHOLDER = 'xxxxxx NEWLINE xxxxxx';

    const COUNTER_MAP = [
        self::ENUM_STYLE_ROMAN => [ 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii', 'xiii', 'xiv', 'xv' ],
        self::ENUM_STYLE_ALPHA => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p' ]
    ];

    protected string $string;
    protected ?LatexFile $latexFile;
    private string $latexBuffer;
    private string $itemCounter;

    public function __construct(string $string = NULL, $latexFile = NULL)
    {
        $this->string = $string ?? '';
        $this->latexFile = $latexFile;
    }

    public function __toString(): string
    {
        return $this->getString();
    }

    public function getString(): string
    {
        return $this->string;
    }

    public function toLatexString(): LatexString
    {
        return new LatexString($this->string);
    }

    public function containsMacros(): bool
    {
        return str_contains($this->string, '\\');
    }

    /** ---------- high-level methods that convert LaTeX content to plain utf-8 text ---------- */

    public function toUtf8String(): string
    {
        return $this->normalizeMacro()->getString();
    }

    public function normalizeMacro(bool $forBib = false): static
    {
        $urlMgr = $this->saveMacros([ 'url', 'href' ]);

        $this->resolveTexOrPdfString();

        // extract and revise url and href
        $this->reviseUrlAndHref($urlMgr);

        // extract and convert math parts
        $mathMgr = $this->reviseMath();

        // convert non-math part
        $this->convertMathFreeString($forBib);

        // re-insert math parts
        $this->restoreSnippets($mathMgr);

        // re-insert urls
        $this->restoreSnippets($urlMgr);

        return $this->trim();
    }

    public function normalizeSimpleMacro(): static
    {
        // extract and convert math parts
        $mathMgr = $this->reviseMath();

        // conversions on non-math parts
        $this->reviseDashes()
            ->reviseDoubleQuotes()
            ->reviseGenitiveS()
            ->replaceLineBreaksByBlank()
            ->reviseTildeAndUnderscore();

        // re-insert math parts
        $this->restoreSnippets($mathMgr);

        return $this->trim();
    }

    public function normalizeAbstract(): static
    {
        // extract and revise url and href
        $urlMgr = $this->saveMacros([ 'url', 'href' ]);
        $this->reviseUrlAndHref($urlMgr);

        // extract and convert math parts
        $mathMgr = $this->reviseMath();

        $this->saveMultipleLineBreaks();

        // conversions on non-math parts
        $this->convertMathFreeString();

        // replace LaTeX diacritics
        $this->convertLatexToUtf8();

        // resolve environments
        $this->resolveEnumerationEnvironments();
        $this->resolveCenterEnvironments();
        $this->resolveQuoteEnvironments();

        // re-insert math parts
        $this->restoreSnippets($mathMgr);

        // re-insert urls
        $this->restoreSnippets($urlMgr);

        // add line-breaks
        $this->replaceNewlinePlaceholders();
        $this->string = preg_replace('/\n\h*\n+/',"\n", $this->string);

        return $this->trim();
    }

    /**
     * collects the default conversion methods for a math-free LaTeX text
     */
    public function convertMathFreeString(bool $forBib  = false): static
    {
        // conversions on non-math parts
        $this->removeEnlargeThisPage();

        $this->expandCiteMacros()
            ->expandLabelReferences();

        $this->string = preg_replace('/\\\\textprime([^a-zA-z])/', '\'$1', $this->string);

        $this->dropPdfStrings()
            ->removeFontMacros()
            ->removeFootnotes()
            ->removeLabels()
            ->removeProtect()
            ->replaceXspace()
            ->reviseDashes($forBib)
            ->reviseDoubleQuotes()
            ->reviseGenitiveS()
            ->removeTexLineBreaks()
            ->replaceLineBreaksByBlank();

        // replace LaTeX diacritics
        $this->convertLatexToUtf8();

        $this->replaceTexBlankSpacesByBlank()
            ->reviseTildeAndUnderscore();

        // replace multiple blank spaces by single blank
        $this->string = preg_replace('/\h+/u', ' ', $this->string);

        // remove empty curly braces
        $this->string = str_replace('{}', '', $this->string);

        $this->string = str_replace(' {et al.} ', ' et al. ', $this->string);

        return $this;
    }

    /**
     * @return string[]
     */
    public function convertAffiliation(): array
    {
        $urlMgr = $this->saveMacros([ 'url', 'href' ]);
        $this->reviseUrlAndHref($urlMgr);

        // conversions on non-math parts
        $this->dropPdfStrings()
            ->removeFontMacros()
            ->removeFootnotes()
            ->removeLabels()
            ->removeProtect()
            ->replaceXspace()
            ->reviseDashes()
            ->reviseDoubleQuotes()
            ->reviseGenitiveS()
            ->replaceTexLineBreaksByComma()
            ->replaceTexBlankSpacesByBlank()
            ->replaceLineBreaksByBlank()
            ->removeCustomFootnoteMarks();

        $this->convertLatexToUtf8();

        $this->restoreSnippets($urlMgr);

        $string = $this->string;

        $string = str_replace("\n", ' ', $string);
        $string = str_replace('and ,', 'and ', $string); // TODO: Why is this necessary?
        $string = str_replace(' \and ', '; ', $string);
        $string = str_replace('\and ', '; ', $string);

        $affiliations = explode('; ', $string);

        $last = $affiliations[count($affiliations) - 1];

        $homepageUrl = '';
        if (StringHelper::startsWith($last,'http://') OR StringHelper::startsWith($last, 'https://')) {
            $homepageUrl = $last;
            unset($affiliations[count($affiliations) - 1]);
            $string = implode('; ', $affiliations);
        }

        // remove braces (often simply used to separate country, see template)
        if (substr_count($string, '{') === 1 AND substr_count($string, '}') === 1) {
            $string = str_replace([ '{', '}' ], '', $string);
        }

        if (substr_count($string, '[') === 1 AND substr_count($string, ']') === 1) {
            $string = str_replace(' [', ' ', $string);
            $string = str_replace('] ', ' ', $string);
            $string = str_replace([ '[', ']' ], '', $string);
        }

        $string = StringHelper::replaceMultipleWhitespacesByOneBlank($string);
        $string = preg_replace('/([^\\\\])\~/', '$1 ', $string);

        $this->string = $string;
        $this->reviseTildeAndUnderscore();
        $string = $this->string;

        return [ trim($string), trim($homepageUrl) ];
    }


    /** --------- low-level methods ----------------------------------------------------------- */

    public function trim(): static
    {
        $this->string = trim($this->string);

        return $this;
    }

    public function expandMacros(): static
    {
        if ($this->latexFile === NULL) {
            return $this;
        }

        $string = $this->string;

        $this->backupLatexFile();

        $this->latexFile->normalizeNewCommands();
        $allCommands = $this->latexFile->getCommands();

        $commandList = [];
        $commandType = [];

        foreach($allCommands as $i=>$cmd) {
            $commandList[$i] = $cmd->getName();
            $commandType[$i] = $cmd->getType();
        }

        foreach($commandList as $i=>$cmd) {

            $index = array_search($cmd, $commandList);
            $declaration = $allCommands[$index]->getDeclaration();

            if ($commandType[$i] === LatexCommand::TYPE_MACRO_WITHOUT_ARG) {
                // the second capture group prevents for example the \e in \emph to be replaced
                $string = preg_replace('/('.preg_quote($cmd).')($|[^a-zA-Z])/', $declaration.'$2', $string);
            }
            else {
                $cmdName = str_replace('\\', '', $cmd);

                $latexString = new LatexString($string);
                $macros = $latexString->getMacros($cmdName);

                // note that by our definition, a macro starts with '\[cmdName]{', i.e. possesses at least one argument
                if ($commandType[$i] !== LatexCommand::TYPE_DEF AND count($macros) > 0) {
                    foreach ($macros as $macro) {
                        $arguments = $macro->getArguments();

                        $replaced = $declaration;
                        foreach ($arguments as $key => $arg) {
                            if (strpos($replaced, '#' . ($key + 1)) === false) {
                                $replaced .= '{' . $arg . '}';
                            }
                            $replaced = str_replace('#' . ($key + 1), $arg, $replaced);
                        }

                        $string = str_replace($macro->getSnippet(), $replaced, $string);
                    }
                } else {
                    $string = preg_replace('/(\\\\' . preg_quote($cmdName) . ')($|[^a-zA-Z])/', $declaration . '$2', $string);
                }
            }
        }

        $this->string = $string;

        $this->restoreLatexFile();

        return $this;
    }

    public function expandCiteMacros(): static
    {
        if (!str_contains($this->string, '\cite') OR $this->latexFile === NULL) {
            return $this;
        }

        $latexString = new LatexString($this->getString());

        foreach ($latexString->getMacros('cite') as $cite) {
            $this->string = str_replace($cite->getSnippet(), $this->latexFile->getCitation($cite->getArgument()), $this->string);
        }

        return $this;
    }

    public function expandLabelReferences(): static
    {
        if (!StringHelper::containsAnyOf($this->string, [ '\ref', '\cref', '\pageref' ]) OR $this->latexFile === NULL) {
            return $this;
        }

        $labels = $this->latexFile->getLabels();

        if (count($labels) === 0) {
            return $this;
        }

        $latexString = new LatexString($this->string);

        foreach([ 'ref', 'cref', 'pageref' ] as $refCommand) {

            foreach ($latexString->getMacros($refCommand) as $ref) {
                $this->string = str_replace(
                    $ref->getSnippet(),
                    $this->latexFile->getLabelReference($ref->getArgument(), $refCommand, true),
                    $this->string
                );
            }
        }

        return $this;
    }

    public function removeFontMacros(int $loops = 3): static
    {
        $string = $this->string;

        foreach(self::TEX_FONT_COMMANDS['switch'] as $key=>$switch) {
            $string = str_replace('{\\'.$switch.' ', '\\'.self::TEX_FONT_COMMANDS['command'][$key].'{', $string);
        }

        $latexString = new LatexString($string, $this->latexFile);

        $formatMacroNames = array_merge(self::TEX_FORMAT_MACROS, self::TEX_FONT_COMMANDS['command'], [ 'sf' ]);
        $formatMacroNames = array_unique($formatMacroNames);

        foreach($formatMacroNames as $macroName) {
            foreach($latexString->getMacros($macroName) as $macro) {
                $string = str_replace($macro->getSnippet(), $macro->getArgument(), $string);
            }
        }

        // look for font-macros that occur after expanding macros
        foreach($formatMacroNames as $macroName) {
            $string = preg_replace('/\\\\'.$macroName.'{([a-zA-Z0-9\-\+]+)}/', '$1', $string);
        }

        // look for font-macros that occur after expanding macros
        foreach($formatMacroNames as $macroName) {
            $string = str_replace('\\'.$macroName, '', $string);
        }

        $loops--;

        if ($loops === 0) {
            $this->string = $string;

            return $this;
        }

        return $this->removeFontMacros($loops);
    }

    /**
     * replaces LaTeX symbols contained in resources/charset/latexMathToUtf8
     */
    public function convertMathSymbols(string $charMapName): static
    {
        if ($this->containsMacros()) {
            $this->string = str_replace('\log\log', '\log \log', $this->string);
            $this->string = Converter::convertCarefully($this->string, $charMapName);
            $this->string = str_replace('\{', '{', $this->string);
        }

        return $this;
    }

    public function convertLatexToUtf8(): static
    {
        if ($this->containsMacros()) {
            $this->string = Converter::convertSimple($this->string, Converter::MAP_LATEX_TO_UTF8);
        }

        return $this;
    }

    /**
     * replaces \(...\) -> $...$  (outside $...$ or \[...\] environments)
     */
    public function unifyInlineMath(): static
    {
        $string = $this->string;

        $placeholderMgr = new PlaceholderManager();

        $string = $placeholderMgr->substitutePatterns([ '/\$(.*)\$/U', '/\\\\\[(.*)\\\\\]/smU' ], $string);

        $string = preg_replace('/\\\\\((.*)\\\\\)/U', '\$$1\$', $string);

        $string = $placeholderMgr->reSubstitute($string);

        $this->string = $string;

        return $this;
    }

    /**
     * replaces \ensuremath - outside math environments with $...$
     *                      - inside math environments with its contents
     */
    public function replaceEnsureMathWithInlineMath(): static
    {
        $string = $this->string;

        $placeholderMgr = new PlaceholderManager();

        $string = $placeholderMgr->substitutePatterns([ '/\$(.*)\$/U', '/\\\\\[(.*)\\\\\]/smU' ], $string);

        $latexString = new LatexString($string);
        $ensureMath = $latexString->getMacros('ensuremath');

        foreach($ensureMath as $math) {
            $string = str_replace($math->getSnippet(), '$'.$math->getArgument().'$', $string);
        }

        $string = str_replace('\ensuremath', '', $string);

        $string = $placeholderMgr->reSubstitute($string);

        $latexString = new LatexString($string);

        foreach($latexString->getMacros('ensuremath') as $ensureMath) {
            $string = str_replace($ensureMath->getSnippet(), $ensureMath->getArgument(), $string);
        }

        $this->string = $string;

        return $this;
    }


    /**
     * replace \operatorname{...} with its contents
     */
    public function reviseOperatorName(): static
    {
        $latexString = new LatexString($this->string);

        foreach($latexString->getMacros('operatorname') as $operatorName) {
            $this->string = str_replace($operatorName->getSnippet(), $operatorName->getArgument(), $this->string);
        }

        return $this;
    }

    /**
     * removes \footnote and \footnotemark macros
     */
    public function removeFootnotes(): static
    {
        $string = $this->string;
        $latexString = new LatexString($string);

        $footnotes = $latexString->getMacros('footnote');

        foreach($footnotes as $footnote) {
            $string = str_replace($footnote->getSnippet(), '', $string);
        }

        $string = preg_replace('/\\\\footref\{.*\}/U', '', $string);
        $string = preg_replace('/\\\\footnotemark\[.*\]/U', '', $string);

        $this->string = $string;

        return $this;
    }

    /**
     * removes \flag macro (from funding)
     */
    public function removeFlags(): static
    {
        $string = $this->string;
        $latexString = new LatexString($string);

        $flags = $latexString->getMacros('flag');

        foreach($flags as $flag) {
            $string = str_replace($flag->getSnippet(), '', $string);
        }

        $this->string = $string;

        return $this;
    }

    /**
     * removes the typical $^1$ and $^{*}$-like footnote marks used to mark authors
     */
    public function removeCustomFootnoteMarks(): static
    {
        $string = $this->string;
        $string = preg_replace('/\$\^[0-9\*]{1}\$/', '', $string);
        $string = preg_replace('/\$\^\{[0-9\*\,]{1,2}\}\$/', '', $string);
        $string = str_replace('¹', '', $string);
        $string = str_replace('²', '', $string);

        $this->string = $string;

        return $this;
    }

    /**
     * removes \textsuperscript{...} macros typically used in author macros
     */
    public function removeTextSuperscript(): static
    {
        $string = $this->string;
        $latexString = new LatexString($string);

        $macros = $latexString->getMacros('textsuperscript');

        foreach($macros as $macro) {
            $string = str_replace($macro->getSnippet(), '', $string);
        }

        $this->string = $string;

        return $this;
    }

    public function removeTexLineBreaks(): static
    {
        $string = $this->string;

        $string = preg_replace(self::TEX_WORD_SEPARATOR_PATTERN, '', $string);
        $string = preg_replace(self::TEX_LINE_BREAKS_PATTERN, ' ', $string);

        $this->string = StringHelper::reduceBlanks($string);

        return $this;
    }

    public function removeLabels(): static
    {
        $this->string = preg_replace(self::TEX_LABEL, '', $this->string);

        return $this;
    }

    public function removeProtect(): static
    {
        $this->string = str_replace('\\protect', '', $this->string);

        return $this;
    }

    public function removeMiniPages(): static
    {
        $latexString = $this->toLatexString();

        foreach($latexString->getEnvironments('minipage') as $minipage) {
            $this->string = str_replace($minipage->getContents(), '', $this->string);
        }

        $this->string = preg_replace('/\\\\begin\{minipage\}\h*\n*\\\\end\{minipage\}/', '', $this->string);

        return $this;
    }

    public function removeEnlargeThisPage(): static
    {
        $latexString = $this->toLatexString();

        foreach($latexString->getMacros('enlargethispage') as $macro) {
            $this->string = str_replace($macro->getSnippet(), '', $this->string);
        }

        return $this;
    }

    /**
     * for affiliations: linebreak in affiliation -> ", "
     */
    public function replaceTexLineBreaksByComma(): static
    {
        $string = $this->string;
        $string = preg_replace(self::TEX_LINE_BREAKS_PATTERN, ', ', $string);
        $string = preg_replace('/,\s*,\s*/', ', ', $string);

        $this->string = StringHelper::reduceBlanks($string);

        return $this;
    }

    /**
     * replace \quad \; etc.
     */
    public function replaceTexBlankSpacesByBlank(): static
    {
        $this->string = preg_replace(self::TEX_BLANK_SPACES, ' ', $this->string);

        return $this;
    }

    /**
     * handling \xspace
     */
    public function replaceXspace(): static
    {
        $string = $this->string;

        $string = preg_replace(self::TEX_XSPACE_PUNCTUATION, self::REPLACEMENT_XSPACE_PUNCTUATION, $string);
        $this->string = preg_replace(self::TEX_XSPACE, self::REPLACEMENT_XSPACE, $string);

        return $this;
    }

    /**
     * replaces multiple ordinary line breaks (i.e. "\n", ...) with one blank space
     */
    public function replaceLineBreaksByBlank(): static
    {
        $this->string = StringHelper::replaceLinebreakByBlank($this->string);
        $this->string = StringHelper::reduceBlanks($this->string);

        return $this;
    }

    /**
     * replaces multiple linebreaks with
     */
    public function saveMultipleLineBreaks(): static
    {
        $this->string = str_replace('\r\n', '\n', $this->string);
        $this->string = str_replace('\n\r', '\n', $this->string);
        $this->string = str_replace('\r', '\n', $this->string);

        $this->string = preg_replace('/\n\h*\n+/sm', self::NEWLINE_PLACEHOLDER, $this->string);

        return $this;
    }

    public function replaceNewlinePlaceholders(): static
    {
        $this->string = str_replace(self::NEWLINE_PLACEHOLDER, "\n\n", $this->string);
        $this->string = preg_replace('/\n\n\h+/', "\n\n", $this->string);
        return $this->trim();
    }

    /**
     * ---, -- -> -
     */
    public function reviseDashes(bool $forBib = false): static
    {
        $string = str_replace('---', ' - ', $this->string);

        // $forBib no longer used
        // "--" -> " -- " should be done in typesetting before
        $this->string = str_replace('--', '-', $string);

        return $this;
    }

    public function reduceBlanks(): static
    {
        $this->string = StringHelper::replaceMultipleWhitespacesByOneBlank($this->string);

        return $this;
    }

    /**
     * 's -> ’s
     */
    public function reviseGenitiveS(): static
    {
        $this->string = str_replace("'s", '’s', $this->string);

        return $this;
    }

    /**
     * \_ -> _  and \~ -> ~, '~' -> ' '
     */
    public function reviseTildeAndUnderscore(): static
    {
        $string = str_replace('\_', '_', $this->string);
        $string = str_replace('\~', '\TILDE_TILDE_TILDE', $string);
        $string = str_replace('~', ' ', $string);
        $string = StringHelper::reduceBlanks($string);
        $this->string = str_replace('\TILDE_TILDE_TILDE', '~', $string);

        return $this;
    }

    /**
     * normalize LaTeX double quotes to "..."
     */
    public function reviseDoubleQuotes(): static
    {
        $string = str_replace('``', '"', $this->string);
        $this->string = str_replace("''", '"', $string);

        return $this;
    }

    /**
     * replace \url and \href macros with pure link
     */
    public function reviseUrlAndHref(PlaceholderManager $urlMgr): static
    {
        $snippets = $urlMgr->getSnippets();

        foreach($snippets as $key=>$string) {

            $latexString = new LatexString($string);

            $macros = array_merge($latexString->getMacros('url'), $latexString->getMacros('href'));

            /** @var LatexMacro $url */
            foreach($macros as $url) {
                $argument = str_replace('\%', '%', $url->getArgument());
                $argument = preg_replace('/\\\\textasciitilde\s*/', '~', $argument);
                $argument = str_replace('\~', '~', $argument);
                $argument = str_replace('\_', '_', $argument);
                $argument = str_replace('\#', '#', $argument);
                $argument = str_replace('{}', '', $argument);
                $argument = str_replace('\allowbreak ', '', $argument);
                $string = str_replace($url->getSnippet(), $argument, $string);
            }

            $snippets[$key] = $string;
        }

        $urlMgr->setSnippets($snippets);

        return $this;
    }

    /**
     * drop the pdf part of \texorpdfstring
     */
    public function dropPdfStrings(): static
    {
        $string = $this->string;

        $latexString = new LatexString($string);

        foreach($latexString->getMacros('texorpdfstring') as $texOrPdf) {
            $string = str_replace($texOrPdf->getSnippet(), $texOrPdf->getArguments()[0], $string);
        }

        $this->string = $string;

        return $this;
    }

    public function resolveTexOrPdfString(): static
    {
        $latexString = new LatexString($this->string);

        $macros = $latexString->getMacros('texorpdfstring');

        foreach($macros as $macro) {
            $arguments = $macro->getArguments();
            if (isset($arguments[0])) {
                $this->string = str_replace($macro->getSnippet(), $arguments[0], $this->string);
            }
        }

        return $this;
    }

    // ---------------- placeholder management -------------------

    /**
     * @param string[] $macroNames
     */
    public function saveMacros(array $macroNames): PlaceholderManager
    {
        $latexString = new LatexString($this->string);

        $macros = [];
        foreach($macroNames as $name) {
            $macros = array_merge($macros, $latexString->getMacros($name));
        }

        $snippets = [];

        /** @var LatexMacro $macro */
        foreach($macros as $macro) {
            $snippets[] = PlaceholderManager::getQuotedPattern($macro->getSnippet());
        }

        $placeholderMgr = new PlaceholderManager();

        $this->string = $placeholderMgr->substitutePatterns($snippets, $this->string);

        return $placeholderMgr;
    }

    public function restoreSnippets(PlaceholderManager $placeholderMgr): static
    {
        $this->string = $placeholderMgr->reSubstitute($this->string);

        return $this;
    }

    /**
     * @return PlaceholderManager
     */
    public function saveMath()
    {
       $placeholderMgr = new PlaceholderManager();

       $string = $this->string;
       $string = $placeholderMgr->substitutePatterns([ '/\\\\\[.*\\\\\]/smU' ], $string);
       $string = $placeholderMgr->substitutePatterns([ '/\$.*\$/smU' ], $string);
       $this->string = $string;

       return $placeholderMgr;
    }

    /**
     * @return PlaceholderManager
     */
    public function reviseMath()
    {
        // unify and save math environments
        $mathMgr = $this->replaceEnsureMathWithInlineMath()
            ->unifyInlineMath()
            ->saveMath();

        // transform these to ASCII with $mathMgr

        $mathSnippets = $mathMgr->getSnippets();

        $transformedMathSnippets = [];

        foreach($mathSnippets as $snippet) {

            $mathSnippet = new MathString($snippet);

            $mathSnippet->convertToText(Converter::MAP_LATEX_MATH_TO_UTF8);

            $transformedMathSnippets[] = $mathSnippet->getString();
        }

        $mathMgr->setSnippets($transformedMathSnippets);

        return $mathMgr;
    }


    /**
     * @param LatexEnvironment[] $enumerations
     * @return string|string[]
     */
    private function replaceItems(array $enumerations, string $enumerationStyle): array|string
    {
        foreach($enumerations as $enum) {
            $enumContentsOld = $enumContents = $enum->getContents();

            if (StringHelper::startsWith($enumContentsOld, '[(a)]')) {
                $enumerationStyle = self::ENUM_STYLE_ALPHA;
                $enumContents = preg_replace('/^\[\(a\)\]/', '', $enumContents);
            }

            $this->itemCounter = 0;

            $enumContents = preg_replace_callback('/\\\\item\s*/', function($match) use ($enumerationStyle){
                $this->itemCounter++;

                switch($enumerationStyle) {
                    case self::ENUM_STYLE_ALPHA:
                        $counter = self::COUNTER_MAP[self::ENUM_STYLE_ALPHA][$this->itemCounter].') ';
                        break;

                    case self::ENUM_STYLE_ROMAN:
                        $counter = self::COUNTER_MAP[self::ENUM_STYLE_ROMAN][$this->itemCounter].') ';
                        break;

                    case self::ENUM_STYLE_NONE:
                        $counter = '- ';
                        break;

                    default:
                        $counter = $this->itemCounter.') ';
                }

                return self::NEWLINE_PLACEHOLDER.$counter;
            }, $enumContents);

            $this->string = str_replace($enumContentsOld, $enumContents, $this->string);
        }

        return $this;
    }

    public function resolveEnumerationEnvironments(): static
    {
        if ($this->latexFile === NULL) {
            return $this;
        }

        $latexFile = $this->latexFile;
        $this->string = preg_replace('/(\n)+\\\\item/', "\n".'\item', $this->string);

        // new line replaces by placeholder
        $this->string = preg_replace('/\n\h*\n/', self::NEWLINE_PLACEHOLDER, $this->string);

        // Do not shorten! $this->toLatexString() may change from line to line!
        $this->replaceItems($this->toLatexString()->getEnvironments('enumerate'), self::ENUM_STYLE_ARABIC);
        $this->replaceItems($this->toLatexString()->getEnvironments('bracketenumerate'), self::ENUM_STYLE_ARABIC);
        $this->replaceItems($this->toLatexString()->getEnvironments('romanenumerate'), self::ENUM_STYLE_ROMAN);
        $this->replaceItems($this->toLatexString()->getEnvironments('alphaenumerate'), self::ENUM_STYLE_ALPHA);
        $this->replaceItems($this->toLatexString()->getEnvironments('itemize'), self::ENUM_STYLE_NONE);

        $string = $this->string;

        foreach(self::ENUMERATION_ENVIRONMENTS as $name) {
            $string = str_replace('\begin{' . $name . '}', '', $string);
            $string = str_replace('\end{' . $name . '}', '', $string);
        }

        $this->string = $string;

        return $this;
    }

    public function resolveCenterEnvironments(): static
    {
        $this->string = str_replace('\begin{center}', self::NEWLINE_PLACEHOLDER, $this->string);
        $this->string = str_replace('\end{center}', self::NEWLINE_PLACEHOLDER, $this->string);

        $this->replaceNewlinePlaceholders();

        return $this;
    }

    public function resolveQuoteEnvironments(): static
    {
        $this->string = str_replace('\begin{quote}', self::NEWLINE_PLACEHOLDER, $this->string);
        $this->string = str_replace('\end{quote}', self::NEWLINE_PLACEHOLDER, $this->string);

        $this->replaceNewlinePlaceholders();

        return $this;
    }

    private function backupLatexFile(): void
    {
        $this->latexBuffer = $this->latexFile->getContents();
    }

    private function restoreLatexFile(): void
    {
        $this->latexFile->setContents($this->latexBuffer);
    }
}