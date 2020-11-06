<?php

namespace Blueways\BwCovidNumbers\Utility;

use Blueways\BwCovidNumbers\Domain\Model\Dto\Graph;

class TcaToGraphUtility
{

    public static function createGraphsFromTca($settings)
    {
        $graphs = [];

        if (!isset($settings['graphs']) || !is_array($settings['graphs'])) {
            return $graphs;
        }

        $dataSource = (int)$settings['dataSource'];

        foreach ($settings['graphs'] as $tca) {
            $graphs[] = self::createGraphFromTca($tca, $dataSource);
        }

        return $graphs;
    }

    public static function createGraphFromTca($tca, $dataSource)
    {
        $graph = new Graph();
        $graph->dataSource = $dataSource;
        $graph->dataOverTime = [];
        $graph->isState = key($tca) === 'state';
        $tca = array_pop($tca);

        $graph->dataType = (int)$tca['dataType'];
        $graph->color = $tca['color'];
        $graph->graphType = (int)$tca['graphType'];

        if ($graph->isState) {
            $graph->IdBundesland = $dataSource === 1 ? $tca['IdBundesland'] : $tca['IdBundeslandLavst'];
            return $graph;
        }

        if ($dataSource === 2) {
            $graph->IdLandkreisLavst = (int)$tca['IdLandkreisLavst'];
            $graph->IdLandkreis = self::getRkiIdFromLavstDistrict((int)$tca['IdLandkreisLavst']);
            return $graph;
        }

        if (is_numeric($tca['IdLandkreis'])) {
            $graph->IdLandkreis = $tca['IdLandkreis'];
            return $graph;
        }

        $graph->Landkreis = $tca['IdLandkreis'];

        return $graph;
    }

    public static function getRkiIdFromLavstDistrict($lavstId)
    {
        $lavstRkiLandkreisMapping = [
            11 => 15001,
            12 => 15002,
            13 => 15003,
            3 => 15084,
            7 => 15088,
            8 => 15089,
            1 => 15082,
            2 => 15083,
            6 => 15087,
            0 => 15081,
            4 => 15085,
            10 => 15091,
            5 => 15086,
            9 => 15090
        ];

        return $lavstRkiLandkreisMapping[$lavstId];
    }

}
