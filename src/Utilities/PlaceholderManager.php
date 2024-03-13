<?php

namespace Dagstuhl\Latex\Utilities;

class PlaceholderManager
{
    const PLACEHOLDER_TEMPLATE = 'xxx VERBATIM x PLACEHOLDER xxx @@INDEX@@ xxx REDLOHECALP x MITABREV xxx';

    const DELIMITERS = [ '#', ';', '/', '&', '+', '!', '_' ];

    private static int $instanceCount = 0;

    private array $storedSnippets = [];
    private string $placeholderTemplate;
    private int $instanceNo;

    /**
     * PlaceholderManager constructor.
     *
     * NOTE: Usually there is no need to define a custom placeholder-template.
     * If you decide to choose one, please make sure that it contains the string '@@INDEX@@'
     * which is replaced by the index of the replacement.
     */
    public function __construct(?string $placeholderTemplate = self::PLACEHOLDER_TEMPLATE)
    {
        self::$instanceCount++;

        $this->instanceNo = self::$instanceCount;

        if (!str_contains($placeholderTemplate, '@@INDEX@@')) {
            echo self::class.': The placeholder template must contain @@INDEX@@';
        }

        $this->placeholderTemplate = $placeholderTemplate;
    }

    private function getPlaceholder(int $index): string
    {
        $instance = str_pad($this->instanceNo, '3', '0', STR_PAD_LEFT);

        return str_replace('@@INDEX@@', $instance.'-'.$index, $this->placeholderTemplate);
    }

    public static function getDelimiter(string $string): string
    {
        foreach(self::DELIMITERS as $char) {
            if (!str_contains($string, $char)) {
                return $char;
            }
        }

        return '@';
    }

    public static function getQuotedPattern(string $string): string
    {
        $string = preg_quote($string);

        $delim = self::getDelimiter($string);

        return $delim.$string.$delim;
    }

    /**
     * @param string[] $patterns
     */
    public function substitutePatterns(array $patterns, string $string): string
    {
        foreach ($patterns as $pattern) {

            $index = count($this->storedSnippets);

            if (preg_match_all($pattern, $string, $matches) > 0) {

                $matches = $matches[0];

                usort($matches, function($a, $b) {
                    return mb_strlen($b) - mb_strlen($a);
                });

                foreach ($matches as $snippet) {
                    $this->storedSnippets[] = [
                        'pattern' => $pattern,
                        'snippet' => $snippet,
                    ];

                    $string = str_replace($snippet, $this->getPlaceholder($index), $string);
                    $index++;
                }
            }
        }

        return $string;
    }

    /**
     * @return string[]
     *
     * allows one to transform the stored snippets
     */
    public function getSnippets(): array
    {
        $snippets = [];

        foreach($this->storedSnippets as $snippet) {
            $snippets[] = $snippet['snippet'];
        }

        return $snippets;
    }

    /**
     * @param string[] $snippets
     *
     * allows one to transform the stored snippets
     */
    public function setSnippets(array $snippets): void
    {
        foreach($snippets as $key=>$snippet) {
            $this->storedSnippets[$key]['snippet'] = $snippet;
        }
    }

    public function reSubstitute(string $string, bool $clearStore = true): string
    {
        for ($index = count($this->storedSnippets) - 1; $index >= 0; $index--) {

            if (!isset($this->storedSnippets[$index])) {

                var_dump($this->storedSnippets);
                echo '<br>';

                echo $index;

                exit();
            }

            $string = str_replace($this->getPlaceholder($index), $this->storedSnippets[$index]['snippet'], $string);

        }

        if ($clearStore) {
            $this->storedSnippets = [];
        }

        return $string;
    }

    public function dumpStore(): void
    {
        var_dump($this->storedSnippets);
    }
}