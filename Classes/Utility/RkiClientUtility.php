<?php

namespace Blueways\BwCovidNumbers\Utility;

use Blueways\BwCovidNumbers\Domain\Model\Dto\Graph;
use DateInterval;
use DatePeriod;
use DateTime;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RkiClientUtility
{

    public static function calc7DayAverage($date, $dataOverTime)
    {
        $keyMinus7Days = $date - 604800000;
        $featuresOfLast7Days = array_filter($dataOverTime,
            static function ($featureKey) use ($date, $keyMinus7Days) {
                return $featureKey > $keyMinus7Days && $featureKey <= $date;
            }, ARRAY_FILTER_USE_KEY);
        return round((array_reduce($featuresOfLast7Days, static function ($a, $b) {
                return $a + $b['AnzahlFall'];
            }) / 7), 1);
    }

    public static function calc7DayWeek($date, $dataOverTime, $population)
    {
        if (!$population || $population === 0) {
            return 0;
        }

        $populationFactor = round($population / 100000, 3);

        $keyMinus7Days = $date - 604800000;
        $featuresOfLast7Days = array_filter($dataOverTime,
            static function ($featureKey) use ($date, $keyMinus7Days) {
                return $featureKey > $keyMinus7Days && $featureKey <= $date;
            }, ARRAY_FILTER_USE_KEY);
        return round((array_reduce($featuresOfLast7Days, static function ($a, $b) {
                return $a + $b['AnzahlFall'];
            }) / $populationFactor), 1);
    }

    public function updateGraphs(&$graphs)
    {
        /**
         * @var integer $key
         * @var Graph $graph
         */
        foreach ($graphs as $key => &$graph) {
            $this->createDataOverTimeForGraph($graph);
            $graph->population = $this->requestPopulationForGraph($graph);
        }

        unset($graph);

        $this->syncGraphsDataOverTime($graphs);
        $this->calculateGraphDataTypes($graphs);
    }

    public function createDataOverTimeForGraph(Graph $graph)
    {
        $features = $this->requestFeaturesForGraph($graph);

        $dataOverTime = [];
        $featuresOverTime = [];

        // create array with readable day as index
        foreach ($features as $key => $feature) {
            $date = date('Y-m-d', ($feature->attributes->Meldedatum / 1000));
            $featuresOverTime[$date] = $feature->attributes;
        }

        // create period from first day until now
        $period = new DatePeriod(
            new DateTime(date('Y-m-d', ($features[0]->attributes->Meldedatum / 1000))),
            new DateInterval('P1D'),
            new DateTime('now')
        );

        // crawl every day
        foreach ($period as $key => $value) {
            $date = $value->format('Y-m-d');

            // look for feature via readable day index
            if (array_key_exists($date, $featuresOverTime)) {
                $feature = $featuresOverTime[$date];
                $dataOverTime[$feature->Meldedatum] = ['AnzahlFall' => $feature->AnzahlFall];
                continue;
            }

            // set 0 if not in dataset
            $dataOverTime[$value->getTimestamp() * 1000] = ['AnzahlFall' => 0];
        }

        $graph->dataOverTime = $dataOverTime;
    }

    public function requestFeaturesForGraph(Graph $location)
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $whereStatement = $location->getWhereStatementForCovidQuery();
        $cacheIdentifier = 'rkiArData' . md5($whereStatement);
        $where = urlencode($whereStatement);

        if (!$apiData = $cache->get($cacheIdentifier)) {
            $apiData = file_get_contents('https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/Covid19_hubv/FeatureServer/0/query?where=' . $where . '&outStatistics=[{"statisticType":"sum","onStatisticField":"AnzahlFall","outStatisticFieldName":"AnzahlFall"}]&groupByFieldsForStatistics=Meldedatum&sqlFormat=none&f=pjson&token=');
            $apiData = json_decode($apiData, false)->features;
            $cache->set($cacheIdentifier, $apiData, [], 82800);
        }

        return $apiData;
    }

    private function syncGraphsDataOverTime($graphs)
    {
        // gather all dates from all graphs
        $allPointsInTime = [];
        /** @var Graph $graph */
        foreach ($graphs as $graph) {
            $allPointsInTime = array_merge($allPointsInTime, array_keys($graph->dataOverTime));
        }

        // fill empty values for new points in time with null
        foreach ($graphs as $key => $graph) {

            foreach ($allPointsInTime as $pointInTime) {
                if (!array_key_exists($pointInTime, $graph->dataOverTime)) {
                    $graph->dataOverTime[$pointInTime] = ['AnzahlFall' => 0];
                }
            }

            ksort($graph->dataOverTime);
        }
    }

    private function requestPopulationForGraph(Graph $graph)
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cacheIdentifier = $graph->getCacheIdentifierForPopulation();

        // try from cache
        if (($population = $cache->get($cacheIdentifier))) {
            return $population;
        }

        // get for state
        if ($graph->isState) {
            $url = "https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/Coronaf%C3%A4lle_in_den_Bundesl%C3%A4ndern/FeatureServer/0/query?where=OBJECTID_1+%3D+11&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&returnCentroid=false&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=";
            $populationData = json_decode(file_get_contents($url));
            $population = $populationData->features[0]->attributes->LAN_ew_EWZ;
        }

        // get for district
        if (!$graph->isState) {

            $where = $graph->getWhereStatementForPopulationQuery();

            $url = "https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/RKI_Landkreisdaten/FeatureServer/0/query?where=" . $where . "&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&returnCentroid=false&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=";
            $populationData = json_decode(file_get_contents($url), true);
            $population = $populationData['features'][0]['attributes']['EWZ'];
        }

        $cache->set($cacheIdentifier, $population, [], 82800000);

        return $population;
    }

    private function calculateGraphDataTypes($graphs)
    {
        /** @var Graph $graph */
        foreach ($graphs as $graph) {
            $previousReportIndex = -1;
            foreach ($graph->dataOverTime as $key => $day) {

                // abort for first day
                if ($previousReportIndex === -1) {
                    $previousReportIndex = $key;
                    $graph->dataOverTime[$key]['sum'] = $day['AnzahlFall'];
                    continue;
                }

                // sum with previous day
                $graph->dataOverTime[$key]['sum'] = $graph->dataOverTime[$previousReportIndex]['sum'] + $day['AnzahlFall'];

                // calculate 7 day average
                $graph->dataOverTime[$key]['avg'] = self::calc7DayAverage($key, $graph->dataOverTime);

                // calculate 7 per 100.000
                $graph->dataOverTime[$key]['week'] = self::calc7DayWeek($key, $graph->dataOverTime, $graph->population);

                $previousReportIndex = $key;
            }
        }
    }
}
