<?php

namespace Dagstuhl\Latex\Metadata;

use Exception;
use ReflectionClass;
use ReflectionProperty;

class MetadataItem
{
    const GROUP_MANDATORY = '_mandatory';
    const GROUP_OPTIONAL = '_optional';
    const GROUP_AT_MOST_1 = '_at-most-1';
    const GROUP_MULTI = '_multi';

    const GROUP_VERBATIM = '_verbatim';
    const GROUP_REMOVE_IF_EMPTY = '_remove-if-empty';
    const GROUP_METADATA_BLOCK = '_metadata-block';


    const GROUP_CALC_IF_EMPTY = '_calc-if-empty';

    const GROUP_MACROS = '_macro';
    const GROUP_ENVIRONMENTS = '_environment';

    const TYPE_MACRO = 'macro';
    const TYPE_ENVIRONMENT = 'environment';
    const TYPE_CALCULATED = 'calculated';

    const NEWLINE = '___newline___';

    private string $name;
    private string $type;
    private string $latexIdentifier;

    /**
     * @var string[]
     */
    private array $properties = [];
    private array $mappings;

    /**
     * @var string[]
     */
    private array $groups;


    public function __construct(array $attributes) {

        $reflect = new ReflectionClass(self::class);

        foreach($reflect->getProperties(ReflectionProperty::IS_PRIVATE) as $prop) {

            $prop = $prop->name;

            if (isset($attributes[$prop])) {
                $this->{$prop} = $attributes[$prop];
            }
            elseif ($prop !== 'properties') {
                die('Missing attribute '.$prop. ' for MetadataItem');
            }
        }

        foreach($this->mappings as $format => $formatMaps) {
            if (!is_array($formatMaps)) {
                die('Mappings for '.$format.' must be an array');
            }
        }

        if (count($this->properties) > 0) {
            foreach ($this->properties as $prop) {
                foreach($this->mappings as $format=>$formatMaps) {
                    $prop = preg_replace('/^\[/', '', $prop);
                    $prop = preg_replace('/\]$/', '', $prop);
                    if (!isset($formatMaps[$prop]) or !is_array($formatMaps[$prop])) {
                        die('ERROR: ' . $prop . ': For each property there must be an array of mappings');
                    }
                }
            }
        }

        if ($this->type === self::TYPE_MACRO AND !in_array(self::GROUP_MACROS, $this->groups)) {
            $this->groups[] = self::GROUP_MACROS;
        }
        elseif ($this->type === self::TYPE_ENVIRONMENT AND !in_array(self::GROUP_ENVIRONMENTS, $this->groups)) {
            $this->groups[] = self::GROUP_ENVIRONMENTS;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLatexIdentifier(): string
    {
        return $this->latexIdentifier;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getMappings(string $format, $name = NULL): array
    {
        if ($name === NULL) {
            return $this->mappings[$format] ?? [];
        }

        return $this->mappings[$format][$name] ?? [];
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function belongsTo(array $groups): bool
    {
        foreach($groups as $group) {
            if (!in_array($group, $this->groups)) {
                return false;
            }
        }

        return true;
    }

    public function belongsToAnyOf(array $groups): bool
    {
        foreach($groups as $group) {
            if (in_array($group, $this->groups)) {
                return true;
            }
        }

        return false;
    }
}