<?php

namespace Dagstuhl\Latex\Metadata;

use Dagstuhl\Latex\Strings\TitleString;
use App\Modules\Names\Name;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Strings\Converter;
use Dagstuhl\Latex\Strings\MetadataString;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Styles\LatexStyle;
use Dagstuhl\Latex\Utilities\PlaceholderManager;

class MetadataReader
{
    const BLOCK_TYPE_RAW = '___raw___';
    const BLOCK_TYPE_NORMALIZED = '___normalized___';

    const FORMAT_UTF8 = 'utf8';
    const FORMAT_LATEX_RAW = 'latex_raw';
    const FORMAT_LATEX_REVISED = 'latex_revised';


    protected LatexFile $latexFile;

    protected LatexStyle $style;

    public function __construct(LatexFile $latexFile)
    {
        $this->latexFile = $latexFile;
        $this->style = $this->latexFile->getStyle();
    }

    public static function isLatexFormat(string $formatString): bool
    {
        return str_contains($formatString, 'latex');
    }

    /**
     * @param array|string $groupsOrName selects either...
     *  - a single metadata-item by its name (when parameter is of type string)
     *  - or groups to filter metadata-items belonging to all specified groups (when parameter is of type array)
     *
     * @param string $format
     * @return array
     *
     * returns extracted metadata
     */
    public function getMetadata(array|string $groupsOrName = [], string $format = self::FORMAT_UTF8): array
    {
        $metadataItems = is_array($groupsOrName)
            ? $this->style->getMetadataItems($groupsOrName)
            : [ $this->style->getMetadataItem($groupsOrName) ];

        $result = [];

        $prefix = $format === self::FORMAT_UTF8
            ? ''
            : $format.'_';

        foreach($metadataItems as $metadataItem) {
            // in latex mode: don't provide calculated items
            if (!(self::isLatexFormat($format) AND $metadataItem->getType() === MetadataItem::TYPE_CALCULATED)) {

                $item = $this->readMetadataItem($metadataItem, $format);

                // don't read empty optional macros like relatedVersionDetails
                if ($item !== NULL) {
                    $result[$prefix . $metadataItem->getName()] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * @param array|string $groupsOrName selects either...
     *  - a single metadata-item by its name (when parameter is of type string)
     *  - or groups to filter metadata-items belonging to all specified groups (when parameter is of type array)
     *
     * @return array
     *
     * returns full LaTeX snippets of specified macro-type(!) metadata items
     */
    public function getMetadataSnippets(array|string $groupsOrName = []): array
    {
        $metadataItems = is_array($groupsOrName)
            ? $this->style->getMetadataItems($groupsOrName)
            : [ $metadataItems = $this->style->getMetadataItem($groupsOrName) ];

        $result = [];

        foreach($metadataItems as $metadataItem) {
            if ($metadataItem->getType() === MetadataItem::TYPE_MACRO) {
                $result[$metadataItem->getName()] = $this->readMetadataSnippet($metadataItem);
            }
        }

        return $result;
    }

    /**
     * @param MetadataItem $metadataItem
     * @param string $format
     * @return string|string[]|string[][]
     *
     * If $raw = true, then the LaTeX code is passed without applying any mapping.
     */
    public function readMetadataItem(MetadataItem $metadataItem, string $format = self::FORMAT_UTF8): array|string
    {
        $properties = $metadataItem->getProperties();
        $type = $metadataItem->getType();

        $isLatexFormat = self::isLatexFormat($format);

        if ($type === MetadataItem::TYPE_CALCULATED) {

            if (count($properties) > 0) {

                $values = [];

                foreach ($properties as $prop) {

                    $value = '';
                    foreach ($metadataItem->getMappings($format, $prop) as $mapping) {
                        $value = $mapping($value, '', $this->latexFile);
                    }

                    foreach($value as $key=>$item) {
                        $values[$key][$prop] = $item;
                    }
                }

                return $values;
            }
            else {
                $value = '';

                foreach ($metadataItem->getMappings($format) as $mapping) {
                    $value = $mapping($value, '', $this->latexFile);
                }

                return $value;
            }
        }
        elseif ($type === MetadataItem::TYPE_MACRO) {

            $latexIdentifier = $metadataItem->getLatexIdentifier();

            // In case of several identifiers: replace each of the identifiers in the LaTeX file with the
            // joined identifier and pass the actual one as option.

            // Example: 'editor|collector|author@role' makes the following replacements
            // 1. \editor{ -> \editor|collector|author[role={editor}]{
            // 2. \collector{ -> \editor|collector|author[role={collector}]{
            // 3. \author{ -> \editor|collector|author[role={author}]{

            $optionName = 'macro';

            $parts = explode('@', $latexIdentifier);

            if (count($parts) == 2) {
                $latexIdentifier = $parts[0];
                $optionName = $parts[1];
            }

            $differentIdentifiers = explode('|', $latexIdentifier);

            if (count($differentIdentifiers) > 1) {
                $content = $this->latexFile->getContents();

                foreach($differentIdentifiers as $identifier) {
                    $content = str_replace('\\'.$identifier.'{', '\\'.$latexIdentifier.'['.$optionName.'={'.$identifier.'}]{', $content);
                }
                $this->latexFile->setContents($content);
            }

            $macros = $this->latexFile->getMacros($latexIdentifier);

            // pre-generate empty array
            $emptyValues = [];
            $emptyValue = '';

            if (count($properties) > 0) {
                foreach ($properties as $prop) {
                    if ($isLatexFormat OR strpos($prop, ':') === false) {
                        // remove surrounding [...] for properties encoded in options
                        $prop = preg_replace('/^\[/', '', $prop);
                        $prop = preg_replace('/\]$/', '', $prop);
                        $emptyValues[$prop] = '';
                    }
                    else {
                        $props = explode(':', $prop);

                        foreach($props as $k=>$val) {
                            $emptyValues[$props[$k]] = '';
                        }
                    }
                }
            }

            // calculate empty value in case of GROUP_CALC_IF_EMPTY (e.g. category)
            elseif (!$isLatexFormat AND $metadataItem->belongsTo([ MetadataItem::GROUP_CALC_IF_EMPTY ])) {
                foreach ($metadataItem->getMappings($format) as $mapping) {
                    $emptyValue = $mapping($emptyValue, '', $this->latexFile);
                }
            }

            if (count($macros) === 0) {

                if ($metadataItem->belongsTo([
                    MetadataItem::GROUP_REMOVE_IF_EMPTY,
                    MetadataItem::GROUP_VERBATIM,
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_MULTI
                ])) {
                    return [];
                }

                $empty = (count($properties) === 0)
                    ? $emptyValue
                    : $emptyValues;

                return $metadataItem->belongsTo([ MetadataItem::GROUP_MULTI ])
                    ? [ $empty ]
                    : $empty;
            }

            $result = [];
            foreach ($macros as $macro) {

                if (count($properties) === 0) {

                    $macroArg = $macro->getArgument();
                    $value = $macroArg;

                    foreach ($metadataItem->getMappings($format) as $mapping) {
                        $value = $mapping($value, $macroArg, $this->latexFile);
                    }

                    if (!$metadataItem->belongsTo([ MetadataItem::GROUP_MULTI ])) {
                        $result = $value;
                    }
                    else {
                        $result[] = $value;
                    }
                }
                else {
                    $macroArgs = $macro->getArguments();

                    $values = $emptyValues;

                    for ($i = 0; $i < count($properties); $i++) {
                        $argument = $macroArgs[$i] ?? '';

                        $value = $argument;

                        $prop = $properties[$i];

                        if (StringHelper::startsWith($prop, '[')) {
                            // remove surrounding [...] for properties encoded in options
                            $prop = preg_replace('/^\[/', '', $prop);
                            $prop = preg_replace('/\]$/', '', $prop);

                            foreach($macro->getOptions() as $option) {
                                $option = trim($option);
                                if (StringHelper::startsWith($option, $prop)) {
                                    $value = $option;
                                    break;
                                }
                            }
                        }

                        foreach ($metadataItem->getMappings($format, $prop) as $mapping) {
                            $value = $mapping($value, $argument, $this->latexFile);
                        }

                        if ($isLatexFormat OR !str_contains($prop, ':')) {
                            $values[$prop] = $value;
                        }
                        else {
                            $props = explode(':', $prop);

                            foreach ($value as $k => $val) {
                                $values[$props[$k]] = $val;
                            }
                        }
                    }

                    $result[] = $values;
                }
            }

            return $result;
        }

        // TYPE ENVIRONMENT
        else {
            $environments = $this->latexFile->getEnvironments($metadataItem->getLatexIdentifier());

            if (count($environments) === 0) {

                return $metadataItem->belongsTo([ MetadataItem::GROUP_MULTI ])
                    ? [ '' ]
                    : '';
            }

            $result = [];
            foreach ($environments as $environment) {

                $contents = $environment->getContents();
                $value = $contents;

                foreach ($metadataItem->getMappings($format) as $mapping) {
                    $value = $mapping($value, $contents, $this->latexFile);
                }

                $result[] = $value;
            }

            if (count($result) === 1) {
                $result = $result[0];
            }

            return $result;
        }
    }

    /**
     * @param MetadataItem $metadataItem
     * @return string|string[]|string[][]|NULL
     *
     * If $raw = true, then the LaTeX code is passed without applying any mapping.
     */
    public function readMetadataSnippet(MetadataItem $metadataItem): array|string|NULL
    {
        $properties = $metadataItem->getProperties();
        $type = $metadataItem->getType();

        if ($type === MetadataItem::TYPE_MACRO) {

            $macros = $this->latexFile->getMacros($metadataItem->getLatexIdentifier());

            if (count($macros) === 0) {

                if ($metadataItem->belongsTo([
                    MetadataItem::GROUP_VERBATIM,
                    MetadataItem::GROUP_REMOVE_IF_EMPTY,
                    MetadataItem::GROUP_MULTI,
                    MetadataItem::GROUP_OPTIONAL
                ])) {
                    return NULL;
                }

                $empty = (count($properties) === 0)
                    ? ''
                    : [];

                return $metadataItem->belongsTo([ MetadataItem::GROUP_MULTI ])
                    ? [ $empty ]
                    : $empty;
            }

            $result = [];
            foreach ($macros as $macro) {

                $snippet = $macro->getSnippet();

                if (!$metadataItem->belongsTo([MetadataItem::GROUP_MULTI])) {
                    $result = $snippet;
                } else {
                    $result[] = $snippet;
                }
            }

            return $result;
        }

        // TODO: add also for environment-type metadata
        return NULL;
    }


    // TODO: Right place for the following?
    public function calculateCopyrightMacro(bool $contentOnly = false): string
    {
        $copyright = $this->latexFile->getMacro('Copyright');

        if ($copyright === NULL) {
            return $contentOnly
                ? ''
                : '\Copyright{}';
        }

        $authors = $this->latexFile->getMacros('author');

        $authorNames = [];
        foreach($authors as $author) {

            $name = $author->getArgument();
            foreach($author->getMacros('footnote') as $footnote) {
                $name = str_replace($footnote->getSnippet(), '', $name);
                $name = preg_replace('/\$\^[0-9\*]+\$/', '', $name);
                $name = preg_replace('/\$\^\{[0-9\*]+\}\$/', '', $name);
            }

            $name = str_replace('\~', 'PPPP-TILDE-PPP', $name);
            $name = str_replace('~', ' ', $name);
            $name = str_replace('PPPP-TILDE-PPP', '\~', $name);

            $name = preg_replace('/\(.*\)/', '', $name);

            $authorNames[] = $name;

        }

        $authorLatex = Name::concatenate($authorNames);

        return $contentOnly
            ? $authorLatex
            : '\Copyright{'.$authorLatex.'}';
    }

    public function calculateAuthorrunningMacro(bool $contentOnly = false): string
    {
        $authors = $this->latexFile->getMacros('author');

        $selectFontPrefix = '{\fontencoding{T5}\selectfont ';
        $authorNames = [];
        $authorHasSelectfontPrefix = [];

        foreach($authors as $key=>$author) {
            $name = trim($author->getArgument());

            $authorHasSelectfontPrefix[$key] = StringHelper::startsWith($name, $selectFontPrefix);

            if ($authorHasSelectfontPrefix[$key] AND substr($name, -1) === '}') {
                $name = str_replace($selectFontPrefix, '', $name);
                $name = mb_substr($name, 0, mb_strlen($name) - 1);
            }

            foreach($author->getMacros('footnote') as $footnote) {
                $name = str_replace($footnote->getSnippet(), '', $name);
                $name = preg_replace('/\$\^[0-9\*]+\$/', '', $name);
                $name = preg_replace('/\$\^\{[0-9\*]+\}\$/', '', $name);
            }
            $name = Converter::convert($name,Converter::MAP_LATEX_TO_UTF8);
            $name = str_replace('~', ' ', $name);
            $name = str_replace('\,', ' ', $name);
            $authorNames[] = self::abbreviateAuthor($name);
        }

        $convertedNames = [];
        foreach($authorNames as $name) {
            $convertedNames[] = Converter::convert($name, Converter::MAP_UTF8_TO_LATEX);
        }

        $authorLatex = Name::concatenate($authorNames);
        $authorLatex = Converter::convert($authorLatex, Converter::MAP_UTF8_TO_LATEX);

        foreach($convertedNames as $key=>$converted) {
            if ($authorHasSelectfontPrefix[$key]) {
                $authorLatex = str_replace($converted, $selectFontPrefix.$converted.'}', $authorLatex);
            }
        }

        $authorLatex = str_replace('@', '\,', $authorLatex);

        return $contentOnly
            ? $authorLatex
            : '\authorrunning{'.$authorLatex.'}';
    }

    public function calculateTitle(): string
    {
        $title = $this->latexFile->getMacro('title');

        $title = new TitleString(trim($title->getArgument()));

        return $title->capitalize();
    }

    public function calculateRunningTitle(): string
    {
        $title = $this->latexFile->getMacro('titlerunning');

        if ($title === NULL) {
            return '';
        }

        $title = new TitleString(trim($title->getArgument()));

        return $title->capitalize();
    }

    public function calculateKeyWords(): string
    {
        $keywordsMacro = $this->latexFile->getMacro('keywords');

        if ($keywordsMacro === NULL) {
            $keywords = '';
        }
        else {
            $keywords = $keywordsMacro->getArgument();

            $placeholderMgr = new PlaceholderManager('XXXXXX@@INDEX@@XXXXXX');

            $keywords = $placeholderMgr->substitutePatterns([ '/\$(.*)\$/U' ], $keywords);
            $keywords = str_replace('w.r.t.', '@@@ wrt @@@', $keywords);

            $keywords = trim($keywords);

            // replace (multiple) whitespaces (blank, linebreak, tab) by one blank
            $keywords = StringHelper::replaceMultipleWhitespacesByOneBlank($keywords);

            // replace ";" and line breaks by the standard separator ","
            $keywords = preg_replace('/(;|\n)/', ',', $keywords);

            // remove blank before separator (multiple blanks cannot occur due to the above)
            $keywords = preg_replace('/ ,/', ',', $keywords);

            // remove "."
            $keywords = preg_replace('/\. |\.$|,$|;$/', '', $keywords);

            // replace separators "---" or " -- " by ","
            $keywords = preg_replace('/---| -- /', ', ', $keywords);

            $keywords = preg_replace('/\\\\and /', ', ', $keywords);

            // remove potentially empty keywords resulting from further replacements
            $keywords = str_replace(',, ', ', ', $keywords);
            $keywords = str_replace(', , ', ', ', $keywords);

            // finally trim the single parts
            $keywords = explode(',', $keywords);
            foreach($keywords as $key=>$keyword) {
                $keyword = new MetadataString($keyword);

                $keywords[$key] = trim(Converter::normalizeLatex($keyword->getString()));
            }

            $keywords = implode(', ', $keywords);
            $keywords = $placeholderMgr->reSubstitute($keywords);

            $keywords = str_replace('\backslash', '\\', $keywords);
            $keywords = str_replace('@@@ wrt @@@', 'w.r.t.', $keywords);
        }

        return StringHelper::replaceMultipleWhitespacesByOneBlank($keywords);
    }

    public function calculateRelatedVersion(string $string): string
    {
        if (stripos($string, 'available at') !== false
            OR stripos($string, 'is available') !== false
            OR StringHelper::startsWith($string, [ 'A full version', 'An extended version', 'A technical report' ])) {


            if (!StringHelper::endsWith($string, '.')) {
                $string .= '.';
            }
        }

        return $string;
    }

    public static function abbreviateAuthor(string $name): string
    {
        $name = preg_replace('/\(.*\)/', '', $name);
        $fullName = new Name($name);
        $firstNames = $fullName->getFirstName();

        $dash = false;

        if (str_contains($firstNames, 'O-joung')) {
            $firstNames = preg_replace('/O-joung($| )/', 'O.$1', $firstNames);
        }

        if (str_contains($firstNames, '-')) {
            $dash = true;
            $firstNames = str_replace('-', ' ', $firstNames);
        }

        $firstNames = explode(' ', $firstNames);

        $abbrev = [];
        foreach ($firstNames as $name) {
            $abbrev[] = mb_substr($name, 0, 1).'.';
        }

        if ($dash) {
            $abbrev = implode('-', $abbrev);
        }
        else {
            $abbrev = implode('@', $abbrev);
        }

        return $abbrev.' '.$fullName->getLastName();
    }

}