<?php


namespace Dagstuhl\Latex\Styles\StyleDescriptions;


use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Styles\StyleDescription;

class LIPIcs_OASIcs_v2021 implements StyleDescription
{

    public static function getMetadataItems(): array
    {
        return [
            [
                'name' => 'title',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'title',  // \title{TitleString}
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseTitleV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK
                ]
            ],
            [
                'name' => 'shortTitle',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'titlerunning',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY
                ]
            ],
            [
                'name' => 'category',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'category',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateCategoryV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_CALC_IF_EMPTY,
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'authors',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'author',
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
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_MULTI,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'authorrunning',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'authorrunning',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeNameListV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK
                ]
            ],
            [
                'name' => 'copyright',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'Copyright',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1', MetadataMappings::class.'::removeBraces' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeNameListV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::NEWLINE
                ]
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
                'name' => 'ccsdesc',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'ccsdesc',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_MULTI,
                    MetadataItem::GROUP_VERBATIM, MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::NEWLINE
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
                'name' => 'relatedVersion',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'relatedversion',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY
                ]
            ],
            [
                'name' => 'relatedVersionDetails',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'relatedversiondetails',
                'properties' => [ 'subcategory', 'url', '[cite]', '[linktext]' ],
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [
                        'subcategory' => [],
                        'url' => [],
                        'cite' => [ MetadataMappings::class.'::getCiteOptionV1' ],
                        'linktext' => [ MetadataMappings::class.'::getLinkTextOptionV1'],
                    ],
                    MetadataReader::FORMAT_LATEX_REVISED => [
                        'subcategory' => [],
                        'url' => [],
                        'cite' => [],
                        'linktext' => [],
                    ],
                    MetadataReader::FORMAT_LATEX_RAW => [
                        'subcategory' => [],
                        'url' => [],
                        'cite' => [ MetadataMappings::class.'::getCiteOptionV1' ],
                        'linktext' => [ MetadataMappings::class.'::getLinkTextOptionV1' ],
                    ],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_MULTI,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY,
                    MetadataItem::GROUP_VERBATIM, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'supplement',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'supplement',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY
                ]
            ],
            [
                'name' => 'supplementDetails',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'supplementdetails',
                'properties' => [ 'category', 'url', '[subcategory]', '[linktext]', '[swhid]', '[cite]' ],
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [
                        'category' => [ MetadataMappings::class.'::getSupplementCategoryV1' ],
                        'url' => [],
                        'subcategory' => [ MetadataMappings::class.'::getSubCategoryOptionV1' ],
                        'linktext' => [ MetadataMappings::class.'::getLinkTextOptionV1' ],
                        'swhid' => [ MetadataMappings::class.'::getSoftwareHeritageIdOptionV1' ],
                        'cite' => [ MetadataMappings::class.'::getCiteOptionV1' ],
                    ],
                    MetadataReader::FORMAT_LATEX_REVISED => [
                        'category' => [],
                        'url' => [],
                        'subcategory' => [],
                        'linktext' => [],
                        'swhid' => [],
                        'cite' => [],
                    ],
                    MetadataReader::FORMAT_LATEX_RAW => [
                        'category' => [],
                        'url' => [],
                        'subcategory' => [ MetadataMappings::class.'::getSubCategoryOptionV1' ],
                        'linktext' => [ MetadataMappings::class.'::getLinkTextOptionV1' ],
                        'swhid' => [ MetadataMappings::class.'::getSoftwareHeritageIdOptionV1' ],
                        'cite' => [ MetadataMappings::class.'::getCiteOptionV1' ],
                    ],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_MULTI,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY,
                    MetadataItem::GROUP_VERBATIM, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'acknowledgements',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'acknowledgements',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'funding',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'funding',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseFundingV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL, MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK, MetadataItem::GROUP_REMOVE_IF_EMPTY, MetadataItem::NEWLINE
                ]
            ],
            [
                'name' => 'articleNo',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'ArticleNo',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],

                'groups' => [
                    MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1
                ]
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
                'name' => 'fullVersionReferences',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateFullVersionReferencesV1' ]
                ],
                'groups' => []
            ],
            [
                'name' => 'license',
                'type' => MetadataItem::TYPE_CALCULATED,
                'latexIdentifier' => '',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateLicenseV1' ]
                ],
                'groups' => []
            ]        ];
    }

    /**
     * @param string $classification
     * @param string $styleName
     * @return array
     *
     * to be overridden in concrete application
     *
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
     *
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
     *
     */
    public static function getFiles($styleName)
    {
        return [];
    }
}