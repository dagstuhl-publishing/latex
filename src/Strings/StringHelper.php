<?php

namespace Dagstuhl\Latex\Strings;

abstract class StringHelper
{
    const PATTERN_LEADING_WHITESPACE ='/^\h*/';

    const PATTERN_CARRIAGE_RETURN = '/\r/';

    const PATTERN_MULTIPLE_BLANK_LINES = '/(\\n){3,}/';
    const REPLACEMENT_MULTIPLE_BLANK_LINES = "\n\n";

    public static function containsAnyOf(string $haystack, array $needles): bool
    {
        foreach($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function extract(string $pattern, string $string, ?int $length = 40, ?bool $startWithBlank = true): array
    {
        $snippets = [];

        if (preg_match_all($pattern, $string, $matches, PREG_OFFSET_CAPTURE) !== false) {

            foreach($matches[0] as $key=>$match) {

                if (!$startWithBlank) {
                    $pos = max($match[1] - floor($length/2), 0);
                }
                else {
                    for ($pos = $match[1]; $pos >= $match[1] - floor($length/2); $pos--) {
                        if (in_array(mb_substr($string, $pos, 1), ["\n", ' '])) {
                            break;
                        }
                    }
                }

                $snippets[] = '...'.mb_substr($string, $pos, mb_strlen($match[0]) + $length).'...';
            }
        }

        return $snippets;
    }

    public static function emphasize(string $pattern, string|array $stringOrArray): string|array
    {
        if (!is_array($stringOrArray)) {
            return preg_replace($pattern, '<b class="_emph">$1</b>',$stringOrArray);
        }
        else {
            $array = $stringOrArray;

            foreach($array as $key=>$string) {
                $array[$key] = self::emphasize($pattern, $string);
            }

            return $array;
        }
    }

    public static function startsWith(string $haystack, string|array $needle): bool
    {
        if (is_array($needle)) {

            foreach($needle as $single) {
                $result = self::startsWith($haystack, $single);

                if ($result === true) {
                    return true;
                }
            }

            return false;
        }

        $length = strlen($needle);

        return (substr($haystack, 0, $length) === $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }


    // -------- replace methods ----------

    public static function replace(string|array $stringOrArray, string $search, string $replace): string|array
    {
        if (!is_array($stringOrArray)) {
            $string = $stringOrArray;

            return str_replace($search, $replace, $string);
        }
        else {
            $array = $stringOrArray;
            foreach($array as $key=>$string) {
                $array[$key] = self::replace($string, $search, $replace);
            }

            return $array;
        }
    }

    public static function reduceBlanks(string $string): string
    {
        return preg_replace('/\h+/u', ' ',$string);
    }

    // -------- remove methods -----------

    public static function removeCarriageReturns(string $string): string
    {
        return preg_replace(self::PATTERN_CARRIAGE_RETURN, '', $string);
    }

    public static function removeLeadingWhitespaces(string $string): string
    {
        return preg_replace(self::PATTERN_LEADING_WHITESPACE, '', $string);
    }

    public static function removeMultipleBlankLines(string $string): string
    {
        return preg_replace(
            self::PATTERN_MULTIPLE_BLANK_LINES,
            self::REPLACEMENT_MULTIPLE_BLANK_LINES,
            $string
        );
    }


    public static function replaceMultipleWhitespacesByOneBlank(string $string): string
    {
        return preg_replace('/\s+/u', ' ', $string);
    }

    public static function replaceLinebreakByBlank(string $string): string
    {
        return preg_replace('/[\n\b\r]+/', ' ', $string);
    }

    public static function replaceTildeByBlank(string|array $stringOrArray): string|array
    {
        if (!is_array($stringOrArray)) {
            $string = $stringOrArray;

            return preg_replace('/\~/', ' ', $string);
        }
        else {
            $array = $stringOrArray;

            foreach($array as $key=>$string) {
                $array[$key] = self::replaceTildeByBlank($string);
            }

            return $array;
        }
    }

}