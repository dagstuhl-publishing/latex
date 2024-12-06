<?php

namespace Dagstuhl\Latex\LatexStructures;

use Dagstuhl\Latex\Strings\StringHelper;

class LatexLabel
{
    const TMP_LABEL_IDENTIFIER = 'tmpLabelaz654kd87cnh34g';

    const UNKNOWN = '\UNKNOWN';

    const LABEL_TYPE_CREF = 'cref';
    const LABEL_TYPE_LATEX = 'latex';

    protected string $key;
    protected string $type;
    protected string $counter = self::UNKNOWN;
    protected string $referencedStructure = self::UNKNOWN;
    protected string $text = self::UNKNOWN;
    protected string $page = self::UNKNOWN;

    public function __construct(string $key, string $auxLabelRaw)
    {
        $this->key = $key;

        $tmp = new LatexString('\\'.self::TMP_LABEL_IDENTIFIER.$auxLabelRaw);
        $label = $tmp->getMacro(self::TMP_LABEL_IDENTIFIER);
        $args = $label?->getArguments();

        if (StringHelper::endsWith($key, '@cref')) {
            $this->type = self::LABEL_TYPE_CREF;

            $parts = explode('][', $args[0]);

            $ref = $parts[0] ?? self::UNKNOWN;
            $ref = str_replace('[', '', $ref);

            $this->referencedStructure = ucfirst($ref);
            preg_match('/\](.*)/', $parts[count($parts)-1], $match);
            $this->counter = $match[1] ?? self::UNKNOWN;
        }
        else {
            $this->type = self::LABEL_TYPE_LATEX;
            $this->counter = $args[0] ?? self::UNKNOWN;
            $this->page = $args[1] ?? self::UNKNOWN;
            $this->text = $args[2] ?? self::UNKNOWN;
            preg_match('/(.*)\./U', $args[3] ?? '', $match);

            $this->referencedStructure = isset($match[1])
                ? ucfirst($match[1])
                : self::UNKNOWN;
        }
    }

    public function getReferenceText(): string
    {
        return match($this->type) {
            self::LABEL_TYPE_LATEX  => $this->counter,
            self::LABEL_TYPE_CREF   => $this->referencedStructure.' '.$this->counter,
            default                 => '\UNKNOWN_LABEL_TYPE'
        };
    }

    public function getPage(): string
    {
        return $this->page;
    }

}