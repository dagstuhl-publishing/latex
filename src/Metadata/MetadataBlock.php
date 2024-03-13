<?php

namespace Dagstuhl\Latex\Metadata;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Styles\LatexStyle;

class MetadataBlock
{
    const PLACEHOLDER_METADATA_BLOCK = '% % % ___ P L A C E H O L D E R --- M E T A D A T A ___ % % %';

    private LatexFile $latexFile;

    private LatexStyle $style;

    /**
     * @var MetadataItem[]
     */
    private array $metadataItems = [];

    private MetadataReader $reader;

    private array $metadataBlock = [];

    /**
     * @param string[] $groups
     */
    public function __construct(LatexFile $latexFile, ?array $groups = [ MetadataItem::GROUP_METADATA_BLOCK ])
    {
        $this->latexFile = $latexFile;
        $this->style = $latexFile->getStyle();
        $this->metadataItems = $this->style->getMetadataItems($groups);
        $reader = $latexFile->getMetadataReader();

        $raw = $reader->getMetadata($groups, MetadataReader::FORMAT_LATEX_RAW);
        $snippets = $reader->getMetadataSnippets([ MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_VERBATIM ]);

        $metadataBlock = [];
        foreach($this->metadataItems as $item) {
            $name = $item->getName();
            $metadataBlock[] = $snippets[$name] ?? $raw[MetadataReader::FORMAT_LATEX_RAW . '_' . $name];
        }

        $this->metadataBlock = $metadataBlock;
    }

    /**
     * substitute all macros from metadata block with placeholder
     */
    private function substituteMetadataMacros(): void
    {
        $reader = $this->latexFile->getMetadataReader();
        $contents = $this->latexFile->getContents();

        $replacement = self::PLACEHOLDER_METADATA_BLOCK;

        foreach($reader->getMetadataSnippets([ MetadataItem::GROUP_METADATA_BLOCK ]) as $item) {

            $item = is_array($item)
                ? $item
                : [ $item ];

            foreach($item as $snippet) {
                if ($snippet !== '' AND $snippet !== NULL) {
                    $contents = str_replace($snippet . "\n", $replacement, $contents);
                    $contents = str_replace($snippet, $replacement, $contents);
                }

                $replacement = '';
            }
        }

        $contents = preg_replace(
            '/[\n\s]+'.preg_quote(self::PLACEHOLDER_METADATA_BLOCK).'[\n\s]+/s',
            "\n\n".self::PLACEHOLDER_METADATA_BLOCK."\n\n",
            $contents
        );

        $this->latexFile->setContents($contents);
    }

    public function replaceInLatexFile(): void
    {
        $this->substituteMetadataMacros();
        $contents = $this->latexFile->getContents();

        // if metadata block is not in header -> move before \begin{document}
        $beginDocumentPos = strpos($contents, '\begin{document}');
        $metadataPos = strpos($contents, self::PLACEHOLDER_METADATA_BLOCK);

        if ($metadataPos > $beginDocumentPos) {
            $contents = str_replace(self::PLACEHOLDER_METADATA_BLOCK, '', $contents);
            $contents = str_replace('\begin{document}', $this->toString()."\n".'\begin{document}', $contents);
        }

        $contents = str_replace(self::PLACEHOLDER_METADATA_BLOCK, $this->toString(), $contents);

        $this->latexFile->setContents($contents);
    }

    public function applyMappings(string $format): static
    {
        foreach($this->metadataBlock as $itemNo=>$entry) {
            $item = $this->metadataItems[$itemNo];

            $properties = $item->getProperties();

            $newEntry = $entry;

            if (!$item->belongsTo([MetadataItem::GROUP_MULTI])) {

                if (count($properties) === 0) {     // single-prop item

                    $mappings = $item->getMappings($format);

                    $original = $entry;

                    foreach ($mappings as $mapping) {
                        $entry = $mapping($entry, $original, $this->latexFile);
                    }

                    $newEntry = $entry;
                } else {

                    foreach ($properties as $prop) {
                        $mappings = $item->getMappings($format, $prop);

                        $newValue = $entry[$prop];
                        $original = $newValue;

                        foreach ($mappings as $mapping) {
                            $newValue = $mapping($newEntry, $original, $this->latexFile);
                        }

                        $newEntry[$prop] = $newValue;
                    }
                }
            } else {

                if (count($properties) === 0) {

                    $mappings = $item->getMappings($format);

                    foreach ($entry as $key => $singleItem) {
                        $original = $singleItem;

                        foreach ($mappings as $mapping) {
                            $singleItem = $mapping($singleItem, $original, $this->latexFile);
                        }

                        $newEntry[$key] = $singleItem;
                    }
                } else {
                    foreach ($entry as $key => $singleItem) {
                        $newItem = $singleItem;

                        if (!$item->belongsTo([ MetadataItem::GROUP_VERBATIM ])) {

                            foreach ($properties as $prop) {

                                $mappings = $item->getMappings($format, $prop);
                                $newValue = $singleItem[$prop];
                                $original = $newValue;

                                foreach ($mappings as $mapping) {
                                    $newValue = $mapping($newValue, $original, $this->latexFile);
                                }

                                $newItem[$prop] = $newValue;
                            }
                        }

                        $newEntry[$key] = $newItem;
                    }
                }
            }

            $this->metadataBlock[$itemNo] = $newEntry;
        }

        return $this;
    }

    /**
     * @param array|string[] $array
     */
    private static function getGroups(array $array): array
    {
        $groups = [];

        foreach($array as $item) {
            $item = trim($item);

            preg_match( '/^\\\\([a-zA-Z0-9\_]*)(\[|\{)/U', $item, $match);

            $groups[$match[1]][] = $item;
        }

        return $groups;
    }

    public function replace(array $array): static
    {
        $groups = self::getGroups($array);

        foreach($this->metadataBlock as $itemNo=>$entry) {

            $item = $this->metadataItems[$itemNo];
            $latexIdentifier = $item->getLatexIdentifier();

            if (isset($groups[$latexIdentifier])) {

                $group = $groups[$latexIdentifier];
                if ($item->belongsTo([ MetadataItem::GROUP_VERBATIM ])) {

                    // prevent the ccsdesc group to be replaced if only the weight changes,  \ccsdesc[weight]{...}
                    if ($latexIdentifier !== 'ccsdesc'
                        OR ($latexIdentifier == 'ccsdesc' AND in_array('ccsdesc', self::getChangedFields($array)))) {

                        $this->metadataBlock[$itemNo] = $item->belongsTo([MetadataItem::GROUP_MULTI])
                            ? $group
                            : $group[0];
                    }
                }
                else {
                    foreach($group as $k=>$line) {
                        $line = preg_replace('/^\\\\([a-zA-Z0-9\_]*)(\[|\{)/U', '', $line);
                        $line = preg_replace('/\}$/', '', $line);
                        $group[$k] = $line;
                    }

                    $this->metadataBlock[$itemNo] = $item->belongsTo([ MetadataItem::GROUP_MULTI ])
                        ? $group
                        : $group[0];
                }
            }
        }

        return $this;
    }

    /**
     * @param string[] $array
     * @return string[]
     */
    public function getChangedFields(array $array, bool $removeCcsDescWeight = true): array
    {
        $groups = self::getGroups($array);

        $changes = [];

        foreach($this->metadataBlock as $itemNo=>$entry) {

            $item = $this->metadataItems[$itemNo];
            $latexIdentifier = $item->getLatexIdentifier();

            $changed = false;

            if (isset($groups[$latexIdentifier])) {

                $group = $groups[$latexIdentifier];
                $oldGroup = $this->metadataBlock[$itemNo];

                if ($item->belongsTo([ MetadataItem::GROUP_MULTI ])) {

                    if (count($group) !== count($oldGroup)) {
                        $changed = true;
                    }
                    else {
                        foreach($oldGroup as $itemKey=>$oldGroupItem) {

                            if ($removeCcsDescWeight) {
                                $oldGroupItem = preg_replace('/\\\\ccsdesc\[[0-9]*\]\{/U', '\ccsdesc{', $oldGroupItem);
                                $group[$itemKey]= preg_replace('/\\\\ccsdesc\[[0-9]*\]\{/U', '\ccsdesc{', $group[$itemKey]);
                            }

                            if ($this->hasDiff($oldGroupItem, $group[$itemKey], $latexIdentifier)) {
                                $changed = true;
                                break;
                            }
                        }
                    }
                }
                else {
                    $oldGroupLatex = '\\' . $latexIdentifier . '{' . $oldGroup . '}';

                    if ($item->belongsTo([MetadataItem::GROUP_REMOVE_IF_EMPTY])) {

                        if ($oldGroupLatex !== $group[0]
                            AND !($oldGroup === '' AND $group[0] === '\\' . $item->getLatexIdentifier() . '{}')) {
                            $changed = true;
                        }
                    } elseif ($oldGroupLatex !== $group[0]) {
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $changes[] = $latexIdentifier;
            }
        }

        return $changes;
    }

    /**
     * @param string[] $array
     * @return string[]
     */
    public function getDiffToLatexFile(array $array): array
    {
        $msg = [];

        foreach(self::getGroups($array) as $latexIdentifier=>$group) {
            $latexMacros = $this->latexFile->getMacros($latexIdentifier);

            foreach($group as $no=>$item) {

                if (!isset($latexMacros[$no])) {
                    // do not complain if an empty category/relatversion/supplement macro is NOT found in LaTeX
                    // i.e. allow empty macros of this kind to be removed completely
                    if ($item !== '\category{}' AND $item !== '\relatedversion{}' AND $item !== '\supplement{}') {
                        $msg[] = '- DIFF: [' . $item . '] not found in LaTeX';
                    }
                }
                else {

                    $latexSnippet = $latexMacros[$no]->getSnippet();

                    // do not complain about missing weight in ccsdesc
                    if (StringHelper::startsWith($latexSnippet, '\\ccsdesc[')) {
                        $latexSnippet = preg_replace('/\\\\ccsdesc\[[0-9]*\]\{/U', '\ccsdesc{', $latexSnippet);
                    }

                    // do not complain if snippets are identical except for an attached comment in inserted snippet
                    $remainder = str_replace($latexSnippet, '', $item);
                    $remainder = trim($remainder);
                    $onlyHasCommentAttached = str_starts_with($remainder, '%');

                    if ($latexSnippet != $item AND !$onlyHasCommentAttached) {
                        $msg[] = '- DIFF: [' . $item . '] expected, but' . "\n";
                        $msg[] = '        [' . $latexMacros[$no]->getSnippet() . '] found';
                    }
                }
            }
        }

        return $msg;
    }

    /**
     * to be overridden in concrete application
     */
    public function hasDiff(string $oldString, string $newString, string $latexIdentifier): bool
    {
        return $oldString !== $newString;
    }

    public function toString(): string
    {
        $lines = [];

        foreach($this->metadataBlock as $itemNo=>$entry) {
            $item = $this->metadataItems[$itemNo];

            $entry = is_array($entry)
                ? $entry
                : [ $entry ];

            if ($item->belongsTo([ MetadataItem::GROUP_VERBATIM ])) {
                foreach($entry as $line) {
                    $lines[] = $line;
                }
            }
            else {
                $removeIfEmpty = $item->belongsTo([ MetadataItem::GROUP_REMOVE_IF_EMPTY ]);

                foreach($entry as $line) {
                    $macroName = '\\'.$item->getLatexIdentifier();

                    $data = is_array($line)
                        ? implode('}{', $line)
                        : $line;

                    if (!(empty($data) AND $removeIfEmpty)) {
                        $lines[] = $macroName . '{' . $data . '}';
                    }
                }
            }

            $lastLine = $lines[count($lines)-1];

            if ($item->belongsTo([ MetadataItem::NEWLINE ]) AND $lastLine !== '') {
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

}