<?php

namespace Dagstuhl\Latex\LatexStructures;

abstract class LatexPatterns
{
    const SIMPLE_LATEX_COMMENTS = [ '/[^\\\\](\%[^\n]*)/sm' ];

    const ENV_BEGIN = '/\\\\begin\{@@NAME@@\}(\[[a-zA-Z\.\|\! ]+\]){0,2}/';

    const MACRO_BEGIN = '/(?!\n.*%.*)\\\\@@NAME@@(\[.*\]){0,1}\s*\{/smU';

    const INLINE_VERBATIM = '/\\\\verb(.{1,1})/';

    const ENVS_VERBATIM = [
        '(\\\\begin\{Verbatim\})(.*)(\\\\end\{Verbatim\})',         // TODO: Verbatim can have additional parameters
        '(\\\\begin\{Verbatim\*\})(.*)(\\\\end\{Verbatim\*\})',
        '(\\\\begin\{verbatim\})(.*)(\\\\end\{verbatim\})',
        '(\\\\begin\{spverbatim\})(.*)(\\\\end\{spverbatim\})',
        '(\\\\begin\{verbatim\*\})(.*)(\\\\end\{verbatim\*\})',
        '(\\\\Verb!)(.*)\n'
    ];

    const ENV_TIK_Z_PICTURE = '/(\\\\begin\{tikzpicture\})(.*)(\\\\end\{tikzpicture\})/smU';
    const ENV_FIGURE = '/(\\\\begin\{figure\})(.*)(\\\\end\{figure\})/smU';

    const PERCENT_SIGN = '/(?<!\\\\)\\\\\%/';   //  find "\%" not being part of "\\%"  TODO: what about "\\\%" ...

    const ENV_PATTERN = '/(\\\\begin\{@@@ NAME @@@\})(.*)(\\\\end\{@@@ NAME @@@\})/smU';

    // names of code environments
    const CODE_ENVIRONMENT_NAMES = [
        'alltt', 'abscode', 'allinlustre', 'allinlustre-figure', 'AnerisPLsmall', 'ainflisting',
        'bull', 'BVerbatim',
        'chorlisting', 'chorallisting', 'code', 'codeblock', 'codeblockcss', 'codejava', 'coq', 'coqlisting', 'clang-figure', 'clang',
        'excerpt', 'excerpt\*', 'easycrypt',
        'FigureVerbatim',
        'granule', 'gql\*', 'haskell', 'Highlighting',
        'InlineVerbatim', 'isabelle',
        'javacode', 'javan', 'javalisting',
        'listingLemma', 'listingJolie', 'lstlisting', 'longcode', 'langlisting', 'leanlisting',
        'minted', 'mcode', 'mzn', 'myequations',
        'numcodejava', 'nicehaskell', 'numpylisting', 'numberedprogram',
        'ocalm', 'OCAMLLISTING',
        'pecan', 'program', 'PYTHONLISTING', 'PYTHONLISTINGGNOLINENO', 'pseudolisting',
        'rustlisting',
        'scalalisting',
        'verbatim', 'VerbatimFigure'
    ];

    const ENV_ISABELLE = '/(\\\\begin\{isabelle\})(.*)(\\\\end\{isabelle\})/smU';

    const ENV_END_NOT_FOLLOWED_BY_COMMAND = '/(\\\\end\{.{1,10}\})\h*(?!\\\\)([A-Za-z\,])/U';

    // the % sign ignores the remaining part of the line, including horizontal whitespace at the beginning of the following line
    // see https://en.wikibooks.org/wiki/LaTeX/Basics#Comments
    const COMMENT_AT_END_OF_LINE = '/\%.*\n\h*/';
    // but blank lines following a comment are preserved
    const COMMENT_FOLLOWED_BY_BLANK_LINE = '/\%.*\n( *)\n/';
    // old expression: const COMMENT_FOLLOWED_BY_BLANK_LINE = '/\%.*\n\s*\n/';

    const NEW_COMMAND_SAME_LINE = '/([^\n ])(\\\\newcommand|\\\\renewcommand)/';

    // definitions, commands, macros "(?:" starts a non-capturing group
    const DEFINE_PATTERN_WITHOUT_ARG = '/(?:\\\\global){0,1}(?:\\\\long){0,1}\h*\\\\def\h*(\\\\[a-zA-Z\.]+)\h*\{/';
    const DEFINE_PATTERN_WITH_ARG = '/(?:\\\\global){0,1}(?:\\\\long){0,1}\h*\\\\def\h*(\\\\[a-zA-Z\.]+)\h*([\#0-9]+)\{/';

    const COMMAND_PATTERN_WITHOUT_ARG = '/(?:\\\\renewcommand|\\\\newcommand|\\\\DeclareMathOperator\*{0,1})\{(\\\\[a-zA-Z]+)\}\{/Ums';
    const COMMAND_PATTERN_WITH_ARG = '/\\\\newcommand\{(\\\\[a-zA-Z]+)\}\[([0-9]+)\]\{/Ums';
    const COMMAND_PATTERN_WITH_OPT_ARG = '/\\\\newcommand\{(\\\\[a-zA-Z]+)\}\[([0-9]+)\]\[(.*)\]\{/Ums';

    /**
     * @return string[]
     */
    private static function getInlineVerbatimPatterns(string $string): array
    {
        $matches = [];
        preg_match_all(self::INLINE_VERBATIM, $string, $matches);

        $patterns = [];

        if (isset($matches[1])) {
            foreach ($matches[1] as $symbol) {
                $patterns[] = '\\\\verb' . preg_quote($symbol) . '(.*)' . preg_quote($symbol);
            }
        }

        return $patterns;
    }

    public static function getVerbatimPattern(string $string): string
    {
        // create pattern for all verbatim environments
        $patterns = array_merge(self::ENVS_VERBATIM, self::getInlineVerbatimPatterns($string));

        $pattern =  implode('|', $patterns);

        foreach([ '/', '#', '@', '+', ';' ] as $delimiter) {    // TODO: enlarge array of possible delimiters

            if (strpos($pattern, $delimiter) === false) {
                $pattern = $delimiter.$pattern.$delimiter;
                break;
            }
        }

        return $pattern.'smU';
    }

    /**
     * @return string[]
     */
    public static function getVerbatimLikePatterns(string $string): array
    {
        $patterns = [
            self::getVerbatimPattern($string),
            self::PERCENT_SIGN,
        ];

        foreach(self::CODE_ENVIRONMENT_NAMES as $name) {
            $patterns[] = str_replace('@@@ NAME @@@', $name, self::ENV_PATTERN);
        }

        return $patterns;
    }

}