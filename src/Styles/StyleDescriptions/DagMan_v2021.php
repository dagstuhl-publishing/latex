<?php

namespace Dagstuhl\Latex\Styles\StyleDescriptions;

use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Metadata\MetadataMappings;
use Dagstuhl\Latex\Metadata\MetadataReader;

class DagMan_v2021 extends LIPIcs_OASIcs_v2021
{
    public static function getMetadataItems()
    {
        $metadataItems = parent::getMetadataItems();

        $metadataItems[] = [
            'name' => 'firstPage',
            'type' => MetadataItem::TYPE_CALCULATED,
            'latexIdentifier' => '',
            'mappings' => [
                MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportFirstPageV1']
            ],
            'groups' => []
        ];

        $metadataItems[] = [
            'name' => 'lastPage',
            'type' => MetadataItem::TYPE_CALCULATED,
            'latexIdentifier' => '',
            'mappings' => [
                MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::getReportLastPageV1']
            ],
            'groups' => []
        ];

        foreach($metadataItems as &$item) {

            if ($item['name'] === 'title') {
                $item = [
                    'name' => 'title',
                    'type' => MetadataItem::TYPE_MACRO,
                    'latexIdentifier' => 'title',
                    'mappings' => [
                        MetadataReader::FORMAT_UTF8 => [ MetadataMappings::class.'::calculateReportsTitleV1' ],
                        MetadataReader::FORMAT_LATEX_REVISED => [ MetadataMappings::class.'::normalizeLineBreaksAndBlanksV1' ],
                        MetadataReader::FORMAT_LATEX_RAW => [],
                    ],
                    'groups' => [
                        MetadataItem::GROUP_MANDATORY, MetadataItem::GROUP_AT_MOST_1,
                        MetadataItem::GROUP_METADATA_BLOCK
                    ]
                ];
            }
        }

        return $metadataItems;
    }
}