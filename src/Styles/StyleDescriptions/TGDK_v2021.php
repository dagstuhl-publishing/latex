<?php

namespace Dagstuhl\Latex\Styles\StyleDescriptions;

use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Styles\StyleDescription;

class TGDK_v2021 implements StyleDescription
{
    public static function getMetadataItems(): array
    {
        $basicMetadataItems = LIPIcs_OASIcs_v2021::getMetadataItems();

        $additionalMetadataItems = [
            [
                'name' => 'dateSubmission',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'DateSubmission',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK
                ]
            ],
            [
                'name' => 'dateAcceptance',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'DateAcceptance',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK
                ]
            ],
            [
                'name' => 'datePublished',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'DatePublished',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_AT_MOST_1,
                    MetadataItem::GROUP_METADATA_BLOCK
                ]
            ],
            [
                'name' => 'sectionAreaEditor',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'SectionAreaEditor',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLatexV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_AT_MOST_1
                ]
            ],
            [
                'name' => 'numberOfSectionAreaEditors',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'SectionAreaNoEds',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::trim' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLatexV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_AT_MOST_1
                ]
            ],
            [
                'name' => 'specialIssueTitle',
                'type' => MetadataItem::TYPE_MACRO,
                'latexIdentifier' => 'SpecialIssue',
                'mappings' => [
                    MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::reviseMacroV1' ],
                    MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLatexV1' ],
                    MetadataReader::FORMAT_LATEX_RAW => [],
                ],
                'groups' => [
                    MetadataItem::GROUP_OPTIONAL,
                    MetadataItem::GROUP_AT_MOST_1
                ]
            ],
        ];

        return array_merge($basicMetadataItems, $additionalMetadataItems);
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
     * @return ?string
     *
     * to be overridden in concrete application
     *
     */
    public static function getPath($styleName): ?string
    {
        return '';
    }

    /**
     * @param string $styleName
     * @return array
     *
     * to be overridden in concrete application
     *
     */
    public static function getFiles($styleName): array
    {
        return [];
    }
}