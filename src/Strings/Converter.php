<?php

namespace Dagstuhl\Latex\Strings;

use Dagstuhl\Latex\Utilities\Filesystem;

abstract class Converter
{
    const MAP_LATEX_MATH_TO_UTF8 = 'latexMathToUtf8';
    const MAP_LATEX_TO_UTF8 = 'latexToUtf8';
    const MAP_UTF8_TO_LATEX = 'utf8ToLatex';
    const MAP_LATEX_TO_ASCII = 'latexToAscii';
    const MAP_UTF8_TO_LATIN1 = 'utf8ToLatin1';
    const MAP_UTF8_TO_ASCII = 'utf8ToAscii';

    /**
     * @var CharMap[]|null
     */
    protected static ?array $maps = NULL;

    protected static bool $isInitialized = false;

    protected static function loadMaps(): void
    {
        // self::_loadLatexToUtf8Map();
        self::loadMap(self::MAP_LATEX_TO_UTF8, 'charsets/latex-to-utf8.txt');
        self::loadMap(self::MAP_UTF8_TO_LATEX, 'charsets/utf8-to-latex.txt');
        self::loadMap(self::MAP_LATEX_TO_ASCII, 'charsets/latex-to-ascii.txt', '|');
        self::loadMap(self::MAP_UTF8_TO_LATIN1, 'charsets/utf8-to-latin1.txt', '|');
        self::loadMap(self::MAP_LATEX_MATH_TO_UTF8, 'charsets/latex-math-to-utf8.txt', ';', true);
        self::loadMap(self::MAP_UTF8_TO_ASCII, 'charsets/utf8-to-ascii.txt');
    }

    private static function loadMap(string $name, string $path, string $separator = ';', bool $trim = false): void
    {
        $file = Filesystem::getResource($path);

        if (empty($file)) {
            echo 'ERROR: empty or non-existent file: '.$path;
            exit();
        }

        $lines = explode("\n", $file);

        $from = [];
        $to = [];

        foreach ($lines as $line) {
            if (!StringHelper::startsWith($line, '#') AND trim($line) != '') {
                $line = explode($separator, $line);

                if ($trim) {
                    $line[0] = trim($line[0]);
                }

                $from[] = $line[0];

                if (isset($line[1])) {
                    $to[] = $line[1];
                }
                else {
                    $to[] = preg_replace('/^\\\\/', '', $line[0]);
                }
            }
        }

        $preConvert = NULL;

        if ($name === static::MAP_LATEX_TO_UTF8) {
            $preConvert = '\Dagstuhl\Latex\Strings\Converter::preLatexToUtf8Conversion';
        }

        self::$maps[$name] = new CharMap($from, $to, $preConvert);
    }

    // IMPORTANT: Should be called by any public method using the maps first!
    public static function init(): void
    {
        if (static::$isInitialized) {
            return;
        }

        mb_internal_encoding('UTF-8');

        static::loadMaps();
        static::$isInitialized = true;
    }

    public static function dumpMap(string $name, string $separator = ';'): string
    {
        self::init();
        return self::$maps[$name]->dump($separator);
    }

    public static function preLatexToUtf8Conversion(string $string): string
    {
        if (str_contains($string, '\\') OR str_contains($string, '{\\')) {
            $string = str_replace('\^{\i}','î', $string);
            $string = str_replace('\u{r}', 'ř', $string);
            $string = str_replace('\\aa{}', 'å', $string);
            $string = str_replace('\"\i ', 'ï', $string);
            $string = str_replace('\dj{}', 'đ', $string);
            $string = str_replace('{\\\'k}', 'ḱ', $string);
            $string = str_replace('\!', '', $string);
            $string = str_replace('\nobreakdash', '', $string);
            $string = str_replace('\footnotesize', '', $string);
            $string = str_replace('\em', '', $string);
            $string = str_replace('\ss,', 'ß,', $string);
            $string = str_replace('\ss{},', 'ß,', $string);
            $string = str_replace('{\" \i}', 'ï', $string);
            $string = str_replace('{\i{}}', 'ı', $string);
            $string = str_replace('\uı', '\u{\i}', $string);
            $string = str_replace('\u\i', '\u{\i}', $string);
            $string = str_replace('{\u \i}', '\u{\i}', $string);
            $string = str_replace('\u{\i}', 'ĭ', $string);
            $string = str_replace('\!', '', $string);
            $string = str_replace('\ss,', 'ß,', $string);
            $string = str_replace('\ss{},', 'ß,', $string);
            $string = str_replace('\ss\\', 'ß\\', $string);
            $string = str_replace('\u{C}', 'C̆', $string);

            $string = preg_replace('/\\\\([^io\&l\%\@,;]{1}) ([a-zA-Z]{1})/', '\\\\$1{$2}', $string);
            //$string = preg_replace('/\\\\u ([a-zA-Z]{1})/', '\\\\u{$2}', $string);
            $string = preg_replace('/\\\\ss{}$/', 'ß', $string);
            $string = preg_replace('/\\\\ss$/', 'ß', $string);
            $string = str_replace("Ko\\'scielski", 'Kościelski', $string);
            $string = str_replace("\'\i\v{c}", 'íč', $string);
            $string = str_replace("\'\i{}", 'í', $string);
            $string = str_replace('{\i{}}', 'ı', $string);
            $string = str_replace('\i{}', 'ı', $string);
            $string = str_replace('\"{n}', 'n̈', $string);
            $string = str_replace('\u{s}', 's̆', $string);
            $string = str_replace("\'{k}", 'ḱ', $string);
            $string = str_replace('{\`y}', 'ỳ', $string);

            $string = str_replace('\c{a}', 'a̧', $string);  // not displayed properly by phpStorm!
            $string = str_replace('\l{a}', 'ła', $string);
            $string = str_replace('\u{Z}', 'Z̆', $string);  // not displayed properly by phpStorm!
            $string = str_replace('{\\r a}', 'å', $string);
            $string = str_replace('\u{g}', 'ğ', $string);
            $string = str_replace('{\`\i}', 'ì', $string);
            $string = str_replace('{\k a}', 'ą', $string);
            $string = str_replace('{\u u}', 'ŭ', $string);
            $string = str_replace('{\"\i}', 'ï', $string);
            $string = preg_replace('/\\\\i$/', 'ı', $string);
            $string = str_replace('\textregistered', '®', $string);
        }

        return $string;
    }

    // IMPORTANT: Do not use for UTF8 to Latex since a pattern that occurs in a former replacement may be replaced again!
    // example { -> \{ -> \backslash{
    public static function convertSimple(string $string, string $mapName): string
    {
        self::init();

        /** @var CharMap $map */
        $map = self::$maps[$mapName];

        $string = $map->preConvert($string);

        $patterns = $map->getPatterns();    // patterns are sorted descending by pattern length

        foreach ($patterns as $pattern) {
            $string = str_replace($pattern, $map->getImageOf($pattern), $string);
        }

        return $string;
    }

    // replace only if a non-alphanumerical value follows (e.g. \ni in \nicefrac is not replaced)
    public static function convertCarefully(string $string, string $mapName): string
    {
        self::init();

        /** @var CharMap $map */
        $map = self::$maps[$mapName];

        $string = $map->preConvert($string);

        $patterns = $map->getPatterns();    // patterns are sorted descending by pattern length

        foreach ($patterns as $pattern) {
            $string = preg_replace('/' . preg_quote($pattern) . '([^A-Za-z0-9]{1,1})/', $map->getImageOf($pattern) . '$1', $string);
            $string = preg_replace('/' . preg_quote($pattern) . '$/', $map->getImageOf($pattern), $string);
        }

        if ($mapName === self::MAP_LATEX_TO_UTF8) {
            $string = str_replace('\c{a}', 'a̧', $string);
        }

        return $string;
    }

    /**
     * @param string|string[] $stringOrArray
     * @param string $mapName
     * @return string|string[]
     */
    public static function convert(string|array $stringOrArray, string $mapName): string|array
    {
        self::init();

        /** @var CharMap $map */
        $map = self::$maps[$mapName];

        $patterns = $map->getPatterns();    // patterns are sorted descending by pattern length

        if (!is_array($stringOrArray)) {

            $string = $stringOrArray;

            $string = $map->preConvert($string);

            $position = 0;

            while ($position < mb_strlen($string)) {

                $positionStep = 1;

                foreach ($patterns as $pattern) {

                    if (mb_substr($string, $position, mb_strlen($pattern)) == $pattern) {
                        $replacement = $map->getImageOf($pattern);

                        $string = mb_substr($string, 0, $position)
                            . $replacement
                            . mb_substr($string, $position + mb_strlen($pattern));

                        $positionStep = mb_strlen($replacement);

                        break;
                    }
                }

                $position += $positionStep;
            }

            return $string;
        } else {
            $array = $stringOrArray;

            foreach ($array as $key => $string) {
                $array[$key] = self::convert($string, $mapName);
            }

            return $array;
        }
    }

    public static function normalizeLatex(string $string): string
    {
        // $string = str_replace('{}', ' ', $string); // CAUSED problem here: Przemys\l{}aw
        $string = StringHelper::replaceLinebreakByBlank($string);
        $string = preg_replace('/{\\\\\' ([a-zA-Z])}/', "\'$1", $string);  // {\' i} -> \'{i}
        $string = str_replace("\' ", "\'", $string);

        // NOTE: Diacritics like \~a shall be converted
        // while ~ for space shall be preserved!
        $string = str_replace('\~', '---backslashTilde---', $string);

        $doNotConvert = ['\\\\', '\!', '\ ', '~', '\,', '\;', '$\sim$', '\_', '[', ']', '\-', '\newline', '\ss', '\footnote', '\emph'];

        foreach ($doNotConvert as $key => $sign) {
            $replacement = 'abababREPLACE' . $key . 'REPLACEababab';
            $string = str_replace($sign, $replacement, $string);
        }

        $string = str_replace('---backslashTilde---', '\~', $string);

        $string = self::convert($string, self::MAP_LATEX_TO_UTF8);
        $string = self::convert($string, self::MAP_UTF8_TO_LATEX);

        foreach ($doNotConvert as $key => $sign) {
            $replacement = 'abababREPLACE' . $key . 'REPLACEababab';
            $string = str_replace($replacement, $sign, $string);
        }

        return $string;
    }

    public static function convertArabicToRomanNumber(string|int $number, bool $lowercase = true): string
    {
        if (is_string($number)) {
            $number = (int)$number;
        }

        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }

        return $lowercase
            ? strtolower($returnValue)
            : $returnValue;
    }
}