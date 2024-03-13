<?php

namespace Dagstuhl\Latex\Styles\StyleDescriptions;

use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Styles\StyleDescription;

class Article implements StyleDescription
{
    public static function getMetadataItems()
    {
        return [
            [
                'name' => 'title',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'title',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [ MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1 ]
            ]
        ];
    }

    public static function getFiles($styleName)
    {
        return [];
    }

    public static function getPath($styleName)
    {
        return '';
    }

    public static function getPackages($classification, $styleName)
    {
        return [];
    }
}