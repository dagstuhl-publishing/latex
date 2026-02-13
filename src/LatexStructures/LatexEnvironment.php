<?php

namespace Dagstuhl\Latex\LatexStructures;

class LatexEnvironment extends LatexString
{
    private string $name;
    private string $header;
    private string $options;

    public array $_log = [];

    private static function readContent(string $string, int $offset, string $name): string
    {
        $contents = '';
        $height = 1;

        $openEnv = '\begin{'.$name.'}';
        $closeEnv = '\end{'.$name.'}';

        $pos = $offset + strlen($openEnv);

        while($height > 0 AND $pos + strlen($closeEnv) < strlen($string)) {

            if (substr($string, $pos, strlen($closeEnv)) === $closeEnv) {
                $height--;
            }
            elseif (substr($string, $pos, strlen($openEnv)) === $openEnv) {
                $height++;
            }

            if ($height > 0) {
                $contents .= substr($string, $pos, 1);
            }

            $pos = $pos + 1;

        }

        return $contents;
    }

    /**
     * @return LatexEnvironment[]
     */
    public static function _getEnvironments(string $name, string $string, LatexFile $latexFile = NULL): array
    {
        $regEx = str_replace('@@NAME@@', preg_quote($name), LatexPatterns::ENV_BEGIN);

        $matches = [];
        $environments = [];

        if (preg_match_all($regEx, $string, $matches, PREG_OFFSET_CAPTURE) !== false) {

            foreach($matches[0] as $key=>$match) {

                $optionsLength = 0;
                $options = '';

                // parse options
                if (isset($matches[1][$key][0])) {
                    $optionsLength = strlen($matches[1][$key][0]);
                    $options = trim($matches[1][$key][0]);
                    $options = preg_replace('/^\[|\]$/', '', $options);
                    $options = trim($options);
                }

                $contents = self::readContent($string, $match[1] + $optionsLength, $name);

                $environments[] = new LatexEnvironment([
                    'name' => $name,
                    'header' => $match[0],
                    'options' => $options,
                    'contents' => $contents,
                    'latexFile' => $latexFile
                ]);
            }
        }

        return $environments;
    }

    public function __construct($attributes)
    {
        $this->name = $attributes['name'];
        $this->header = $attributes['header'];
        $this->options = $attributes['options'];

        return parent::__construct($attributes['contents'], $attributes['latexFile']);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContents($withoutComments = false): string
    {
        return $this->getValue($withoutComments);
    }

    public function setContents($newContents, $writeToLatexFile = false): bool
    {
        return $this->setValue($newContents, $writeToLatexFile);
    }

    public function getSnippet(): string
    {
        return $this->header.$this->getContents().'\end{'.$this->name.'}';
    }

    public function contains($string): bool
    {
        return substr_count($this->getContents(), $string) > 0;
    }

    public function getOptionsString(): string
    {
        return $this->options;
    }
    
    // parameters are terms in {...} following the environment name (e.g. restatables have parameters)
    public function getParameters(): array
    {
        $offset = strpos($this->getContents(), '{');
        return LatexMacro::getMacroArgs($this->getContents(), $offset + 1);
    }

    public function writeToLatexFile($newContents): void
    {
        $this->latexFile->setContents(str_replace($this->getContents(), $newContents, $this->latexFile->getContents()));
    }
}