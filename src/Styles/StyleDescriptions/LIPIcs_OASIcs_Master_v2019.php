<?php

namespace Dagstuhl\Latex\Styles\StyleDescriptions;

use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Styles\StyleDescription;

class LIPIcs_OASIcs_Master_v2019 implements StyleDescription
{
    public static function getMetadataItems()
    {
        return [
            [
                'name' => 'title',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateTitleLipicsFrontmatterV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'abstract',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateTitleLipicsFrontmatterV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'keywords',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateTitleLipicsFrontmatterV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'eventTitle',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'EventTitle',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [ MetadataItem::GROUP_AT_MOST_1 ]
            ],
            [
                'name' => 'authors',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'editor',
                'properties' => [ 'name', 'affiliation:homepageUrl', 'email', 'orcid', 'funding' ],
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [
                        'name' => [ MetadataMappings::class.'::reviseAuthorV1' ],
                        'affiliation:homepageUrl' => [ MetadataMappings::class.'::reviseAffiliationV1' ], // ':' -> split
                        'email' => [ MetadataMappings::class.'::reviseEmailV1' ],
                        'orcid' => [],
                        'funding' => [ MetadataMappings::class.'::reviseFundingV1' ]
                    ],
                    MetadataReader::FORMAT_LATEX_REVISED => [
                        'name' => [ MetadataMappings::class.'::normalizeNameV1' ],
                        'affiliation:homepageUrl' => [ MetadataMappings::class.'::normalizeNameV1' ],
                        'email' => [],
                        'orcid' => [],
                        'funding' => []
                    ],
                    MetadataReader::FORMAT_LATEX_RAW => [
                        'name' => [],
                        'affiliation:homepageUrl' => [], // ':' -> split
                        'email' => [],
                        'orcid' => [],
                        'funding' => []
                    ]
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_MULTI
                ]
            ],
            [
                'name' => 'ccsdesc',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'ccsdesc',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_MULTI
                ]
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