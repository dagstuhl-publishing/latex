<?php

namespace Dagstuhl\Latex\Styles\StyleDescriptions;

use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Styles\StyleDescription;

class DagstuhlReports_v2022 implements StyleDescription
{
    public static function getMetadataItems()
    {
        return [
            [
                'name' => 'title',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => 'title',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateReportsTitleV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [ MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1 ]
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
                'name' => 'authors',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'author|editor|collector@role',
                'properties' => [ 'name', 'affiliation:homepageUrl', 'email', 'orcid', 'funding', '[role]' ],
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [
                        'name' => [ MetadataMappings::class.'::reviseAuthorV1' ],
                        'affiliation:homepageUrl' => [ MetadataMappings::class.'::reviseAffiliationV1' ], // ':' -> split
                        'email' => [ MetadataMappings::class.'::reviseEmailV1' ],
                        'orcid' => [],
                        'funding' => [ MetadataMappings::class.'::reviseFundingV1' ],
                        'role' => [ MetadataMappings::class.'::getRoleOptionV1' ]
                    ],
                    MetadataReader::FORMAT_LATEX_REVISED => [
                        'name' => [ MetadataMappings::class.'::normalizeNameV1' ],
                        'affiliation:homepageUrl' => [ MetadataMappings::class.'::normalizeNameV1' ],
                        'email' => [],
                        'orcid' => [],
                        'funding' => [],
                        'role' => []
                    ],
                    MetadataReader::FORMAT_LATEX_RAW => [
                        'name' => [],
                        'affiliation:homepageUrl' => [], // ':' -> split
                        'email' => [],
                        'orcid' => [],
                        'funding' => [],
                        'role' => []
                    ]
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_MULTI,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'seminarNo',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'seminarnumber',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [ MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1 ]
            ],
            [
                'name' => 'abstract',
                'type' => MetadataItem::TYPE_ENVIRONMENT,
                'latexIdentifier' => 'abstract',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseAbstractV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1
                ]
            ],
            [
                'name' => 'keywords',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'keywords',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY,
                    MetadataItem::GROUP_AT_MOST_1, MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::NEWLINE
                ]
            ],

            [
                'name' => 'firstPage',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportFirstPageV1']
                ],
                'groups' => []
            ],

            [
                'name' => 'lastPage',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportLastPageV1']
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
            [
                'name' => 'doi',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'DOI',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ ]
                ],
                'groups' => []
            ],

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