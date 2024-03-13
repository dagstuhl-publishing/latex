<?php

namespace Dagstuhl\Latex\Styles\StyleDescriptions;

use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Styles\StyleDescription;

class DagstuhlReports_Master implements StyleDescription
{

    public static function getMetadataItems()
    {
        return [

            [
                'name' => 'title',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'subtitle',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportFrontmatterTitleV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'abstract',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'subtitle',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportFrontmatterTitleV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'keywords',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'subtitle',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportFrontmatterKeywordsV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'articleNo',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateArticleNoFrontmatter' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'pages',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculatePagesFromPdfV1' ]
                ],
                'groups' => []
            ],

        ];
    }

    /**
     * @param string $classification
     * @param string $styleName
     * @return array
     *
     * to be overridden in concrete application
     */
    public static function getPackages($classification, $styleName)
    {
        return [];
    }

    /**
     * @param string $styleName
     * @return array
     *
     * to be overridden in concrete application
     */
    public static function getPath($styleName)
    {
        return [];
    }

    /**
     * @param string $styleName
     * @return array
     *
     * to be overridden in concrete application
     */
    public static function getFiles($styleName)
    {
        return [];
    }
}