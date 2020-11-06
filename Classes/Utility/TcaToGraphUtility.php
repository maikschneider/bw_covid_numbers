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
            $graph->IdLandkreis = $tca['IdLandkreisLavst'];
            return $graph;
        }

        if (is_numeric($tca['IdLandkreis'])) {
            $graph->IdLandkreis = $tca['IdLandkreis'];
            return $graph;
        }

        $graph->Landkreis = $tca['IdLandkreis'];

        return $graph;
    }

}
