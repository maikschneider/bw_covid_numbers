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

        foreach ($settings['graphs'] as $tca) {
            $graphs[] = self::createGraphFromTca($tca);
        }

        return $graphs;
    }

    public static function createGraphFromTca($tca)
    {
        $graph = new Graph();
        $graph->isState = key($tca) === 'state';
        $tca = array_pop($tca);

        $graph->dataType = (int)$tca['dataType'];
        $graph->color = $tca['color'];
        $graph->graphType = (int)$tca['graphType'];

        if ($graph->isState) {
            $graph->IdBundesland = $tca['IdBundesland'];
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
