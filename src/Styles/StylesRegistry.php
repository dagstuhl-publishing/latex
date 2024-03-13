<?php

namespace Dagstuhl\Latex\Styles;

use Dagstuhl\Latex\Styles\StyleDescriptions\Article;
use Dagstuhl\Latex\Styles\StyleDescriptions\DagMan_v2021;
use Dagstuhl\Latex\Styles\StyleDescriptions\DagstuhlReports_Master;
use Dagstuhl\Latex\Styles\StyleDescriptions\DagstuhlReports_v2018b;
use Dagstuhl\Latex\Styles\StyleDescriptions\DagstuhlReports_v2022;
use Dagstuhl\Latex\Styles\StyleDescriptions\DARTS_v2019;
use Dagstuhl\Latex\Styles\StyleDescriptions\DARTS_v2021;
use Dagstuhl\Latex\Styles\StyleDescriptions\LIPIcs_OASIcs_Master_v2019;
use Dagstuhl\Latex\Styles\StyleDescriptions\LIPIcs_OASIcs_Master_v2021;
use Dagstuhl\Latex\Styles\StyleDescriptions\LIPIcs_OASIcs_v2019;
use Dagstuhl\Latex\Styles\StyleDescriptions\LIPIcs_OASIcs_v2021;
use Dagstuhl\Latex\Styles\StyleDescriptions\TGDK_v2021;

class StylesRegistry
{
    public static function getDescriptionFor($name)
    {
        switch($name) {

            case 'article':
                return Article::class;

            case 'lipics-v2019':
            case 'oasics-v2019':
                return LIPIcs_OASIcs_v2019::class;

            case 'lipics-v2021':
            case 'oasics-v2021':
            case 'lites-v2021':
                return LIPIcs_OASIcs_v2021::class;

            case 'tgdk-v2021':
                return TGDK_v2021::class;

            case 'lipicsmaster-v2019':
            case 'oasicsmaster-V2019':
                return LIPIcs_OASIcs_Master_v2019::class;

            case 'lipicsmaster-v2021':
            case 'oasicsmaster-v2021':
            case 'litesmaster-v2021':
            case 'tgdkmaster-v2021':
                return LIPIcs_OASIcs_Master_v2021::class;

            case 'darts-v2021':
                return DARTS_v2021::class;

            case 'darts-v2019':
                return DARTS_v2019::class;

            case 'dagrep-v2021':
            case 'dagrep-v2019':
            case 'dagrep-v2018b':
            case 'dagrep-v2018':
            case 'dagman-v2018':
                return DagstuhlReports_v2018b::class;

            case 'dagrep-v2022':
                return DagstuhlReports_v2022::class;

            case 'dagrep-master':
            case 'dagrep-master-v2021':
            case 'dagrep-master-v2022':
            case 'dagman-master':
            case 'dagman-master-v2021':
                return DagstuhlReports_Master::class;

            case 'dagman-v2021':
                return DagMan_v2021::class;
        }

        die('Latex StylesRegistry ERROR: No style description found for documentclass: '.$name);
    }
}