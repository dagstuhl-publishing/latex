<?php

namespace Dagstuhl\Latex\LatexStructures;

class LatexCommand
{
    // macros that should not be deleted even if they do not occur explicitly
    public const DO_NOT_DELETE_MACROS = [
        '\mycommstyle',
        '\mycommfont',
        '\mycommentfnt',
        '\mycomfn',
        '\code',
        '\arraystretch',
        '\problem',
        '\UrlBreaks',
        '\myalgorithmCommSty',
        '\twopartdef',
        '\lstlistingname',
        '\commentFont',
        '\alglinenumber',
        '\passOptions',
        '\lstlanguagefiles',
        '\ScoreOverhang',
        '\proofSkipAmount',
        '\hmmax',
        '\bmmax'
    ];

    public const TYPE_DEF = 'def';

    public const TYPE_DEF_WITH_ARG = 'def-with-arg';
    public const TYPE_MACRO_WITHOUT_ARG = 'macro-no-arg';
    public const TYPE_MACRO_WITH_ARG = 'macro-with-arg';
    public const TYPE_MACRO_OPT_ARG = 'macro-opt-arg';

    protected string $name;
    protected string $numberOfArguments;
    protected string $optionalArgument;
    protected string $type;
    protected string $declaration;
    protected string $snippet;
    protected LatexString $latexString;

    public function __construct($attributes)
    {
        $this->name = $attributes['name'];
        $this->numberOfArguments = $attributes['numberOfArguments'];
        $this->optionalArgument = $attributes['optionalArgument'];
        $this->type = $attributes['type'] ?? 'command';
        $this->declaration = $attributes['declaration'];
        $this->snippet = $attributes['snippet'];
        $this->latexString = $attributes['latexString'];
    }

    /**
     * @param LatexString $latexString
     * @return self[]
     */
    public static function getCommands(LatexString $latexString): array
    {
        $contents = $latexString->getValue(true);

        $commands = [];

        $matches = [];

        if (preg_match_all(LatexPatterns::DEFINE_PATTERN_WITHOUT_ARG, $contents, $matches, PREG_OFFSET_CAPTURE) !== false) {

            foreach($matches[0] as $key=>$match) {
                $macroStartsAt = $match[1];
                $argStartsAt = strlen($match[0]) + $macroStartsAt;
                $argEndsAt = NULL;  // will be evaluated by getDefinition

                $commands[] = new self([
                    'name' => $matches[1][$key][0],
                    'declaration' => self::getCommandDeclaration($contents, $argStartsAt, $argEndsAt),
                    'numberOfArguments' => 0,
                    'type' => self::TYPE_DEF,
                    'optionalArgument' => '',
                    'snippet' => trim(substr($contents, $macroStartsAt, $argEndsAt-$macroStartsAt+1)),
                    'latexString' => $latexString
                ]);
            }
        }

        $matches = [];

        if (preg_match_all(LatexPatterns::DEFINE_PATTERN_WITH_ARG, $contents, $matches, PREG_OFFSET_CAPTURE) !== false) {

            foreach($matches[0] as $key=>$match) {
                $macroStartsAt = $match[1];
                $argStartsAt = strlen($match[0]) + $macroStartsAt;
                $argEndsAt = NULL;  // will be evaluated by getDefinition

                $numberOfArgs = $matches[2][$key][0];
                $numberOfArgs = explode('#', $numberOfArgs);
                $numberOfArgs = $numberOfArgs[count($numberOfArgs)-1];

                $commands[] = new self([
                    'name' => $matches[1][$key][0],
                    'declaration' => self::getCommandDeclaration($contents, $argStartsAt, $argEndsAt),
                    'numberOfArguments' => $numberOfArgs,
                    'optionalArgument' => '',
                    'type' => self::TYPE_DEF_WITH_ARG,
                    'snippet' => trim(substr($contents, $macroStartsAt, $argEndsAt-$macroStartsAt+1)),
                    'latexString' => $latexString
                ]);
            }
        }

        $matches = [];

        if (preg_match_all(LatexPatterns::COMMAND_PATTERN_WITHOUT_ARG, $contents, $matches, PREG_OFFSET_CAPTURE) !== false) {

            foreach($matches[0] as $key=>$match) {
                $macroStartsAt = $match[1];
                $argStartsAt = strlen($match[0]) + $macroStartsAt;
                $argEndsAt = NULL;  // will be evaluated by getDefinition

                $commands[] = new self([
                    'name' => $matches[1][$key][0],
                    'declaration' => self::getCommandDeclaration($contents, $argStartsAt, $argEndsAt),
                    'numberOfArguments' => 0,
                    'type' => self::TYPE_MACRO_WITHOUT_ARG,
                    'optionalArgument' => '',
                    'snippet' => trim(substr($contents, $macroStartsAt, $argEndsAt-$macroStartsAt+1)),
                    'latexString' => $latexString
                ]);
            }
        }

        $matches = [];

        if (preg_match_all(LatexPatterns::COMMAND_PATTERN_WITH_ARG, $contents, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach($matches[0] as $key=>$match) {
                $macroStartsAt = $match[1];
                $argStartsAt = strlen($match[0]) + $macroStartsAt;
                $argEndsAt = NULL;  // will be evaluated by getDefinition

                $commands[] = new self([
                    'name' => $matches[1][$key][0],
                    'declaration' => self::getCommandDeclaration($contents, $argStartsAt, $argEndsAt),
                    'numberOfArguments' => $matches[2][$key][0],
                    'optionalArgument' => '',
                    'type' => self::TYPE_MACRO_WITH_ARG,
                    'snippet' => trim(substr($contents, $macroStartsAt, $argEndsAt-$macroStartsAt+1)),
                    'latexString' => $latexString
                ]);
            }
        }

        $matches = [];

        if (preg_match_all(LatexPatterns::COMMAND_PATTERN_WITH_OPT_ARG, $contents, $matches, PREG_OFFSET_CAPTURE) !== false) {

            foreach($matches[0] as $key=>$match) {
                $macroStartsAt = $match[1];
                $argStartsAt = strlen($match[0]) + $macroStartsAt;
                $argEndsAt = NULL;  // will be evaluated by getDefinition

                $commands[] = new self([
                    'name' => $matches[1][$key][0],
                    'declaration' => self::getCommandDeclaration($contents, $argStartsAt, $argEndsAt),
                    'numberOfArguments' => $matches[2][$key][0],
                    'optionalArgument' => $matches[3][$key][0],
                    'type' => self::TYPE_MACRO_OPT_ARG,
                    'snippet' => trim(substr($contents, $macroStartsAt, $argEndsAt-$macroStartsAt+1)),
                    'latexString' => $latexString
                ]);
            }
        }

        return $commands;
    }

    private static function getCommandDeclaration(string $string, int $offset, &$argEndsAt = NULL): string
    {
        $value = '';
        $height = 1;

        $pos = $offset;
        $nextChar = substr($string, $pos, 1);
        $lastChar = NULL;

        $len = strlen($string);

        while($height > 0 AND $pos < $len) {

            $char = $nextChar;

            $twoPrecedingChars = $pos > 2
                                ? substr($string, $pos-2, 2)
                                : '';

            // new line causes problems since \\} will be misinterpreted as the subsequent characters \ and \}
            // TODO: handle \\\} correctly

            if ($char == '}' AND ($lastChar != '\\' OR $twoPrecedingChars == '\\\\')) {
                $height--;
            }
            elseif ($char == '{' AND ($lastChar != '\\' OR $twoPrecedingChars == '\\\\')) {
                $height++;
            }

            if ($height > 0) {
                $value .= $char;
            }

            $pos = $pos + 1;

            $lastChar = $char;

            if ($pos < $len) {
                $nextChar = substr($string, $pos, 1);
            }
        }

        if ($pos == strlen($string)) {
            // echo '<hr>Could not parse macro properly! Please check: '.$value.'<hr>';
        }

        $argEndsAt = $pos - 1;

        return $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDeclaration(): string
    {
        return $this->declaration;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function getLatexString(): LatexString
    {
        return $this->latexString;
    }

    public function isUsed($string = NULL): bool
    {
        if ($string === NULL) {
            $string = $this->getLatexString()->getValue();
        }

        if (str_contains($this->snippet, '\\renewcommand')
            OR in_array($this->name, self::DO_NOT_DELETE_MACROS)
            OR str_contains($this->name, 'autorefname')
            OR str_contains($string, '\begin{' . str_replace('\\', '', $this->name) . '}')) {

            return true;    // package autoref can be configured via commands that will never be called
        }

        return $this->countOccurrences($string) > 1; // one occurrence in declaration itself
    }

    public function countOccurrences(string $string = NULL): int
    {
        if ($string === NULL) {
            $string = $this->getLatexString();
        }

        $matches = [];

        $pattern = '/'.preg_quote(trim($this->getName())).'(?![a-zA-Z])/';

        preg_match_all($pattern, $string,$matches);

        return count($matches[0]);
    }

    public function remove(): void
    {
        $latexString = $this->getLatexString();
        $snippet = $this->getSnippet();

        $contents = $latexString->getValue();
        $contents = str_replace($snippet."\n", '', $contents);
        $contents = str_replace($snippet, '', $contents);

        $latexString->setValue($contents);
    }
}