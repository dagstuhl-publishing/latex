<?php

namespace Dagstuhl\Latex\Strings;

class CharMap
{
    /**
     * @var string[]
     */
    protected array $from = [];

    /**
     * @var string[]
     */
    protected array $to = [];

    protected ?string $preConversion = NULL;

    public function __construct(?array $from = [], ?array $to = [], string $preConversion = NULL)
    {
        if (count($from) !== count($to)) {
            die('ERROR: Unable to construct CharMap');
        }

        $this->from = $from;
        $this->to = $to;
        $this->preConversion = $preConversion;
    }

    public function add(string $from, string $to): void
    {
        $this->from[] = $from;
        $this->to[] = $to;
    }

    public function has(string $element): bool
    {
        return in_array($element, $this->from);
    }

    public function getImageOf(string $element): string
    {
        $key = array_search($element, $this->from);

        return $key !== false
            ? $this->to[$key]
            : '';
    }

    public function preConvert(string $string): string
    {
        return $this->preConversion !== NULL
            ? call_user_func($this->preConversion, $string)
            : $string;
    }

    /**
     * @return string[]
     */
    public function getPatterns(): array
    {
        $patterns = $this->from;

        // sort patterns by string length which is essential for
        // properly replacing latex entities (e.g. "a and \"a)
        usort($patterns, function($a, $b){
            return mb_strlen($b) - mb_strlen($a);
        });

        return $patterns;
    }

    /**
     * @param string $separator
     * @return string
     */
    public function dump(string $separator = ';'): string
    {
        $list = $this->getPatterns();

        $dump = [];
        $known = [];
        foreach($list as $element) {

            if (!in_array($element, $known)) {
                $dump[] = $element.$separator.$this->getImageOf($element);
                $known[] = $element;
            }
        }

        return implode("\n", $dump);
    }

}