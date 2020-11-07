<?php

namespace Blueways\BwCovidNumbers\Utility;

use Blueways\BwCovidNumbers\Domain\Model\Dto\Graph;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LavstClientUtility
{

    /**
     * @var array
     */
    protected $lavstResponse;

    /**
     * @var array
     */
    protected $dataOverTimes;

    public static function calc7DayAverage($date, $dataOverTime)
    {
        $keyMinus7Days = $date - 604800000;
        $featuresOfLast7Days = array_filter($dataOverTime,
            static function ($featureKey) use ($date, $keyMinus7Days) {
                return $featureKey > $keyMinus7Days && $featureKey <= $date;
            }, ARRAY_FILTER_USE_KEY);
        return round((array_reduce($featuresOfLast7Days, static function ($a, $b) {
                return $a + $b['AnzahlFall'];
            }) / count($featuresOfLast7Days)), 1);
    }

    public function updateGraphs($graphs)
    {
        if (!$graphs) {
            return;
        }

        $lavstData = $this->requestLavstData();
        $dataOverTimes = $this->generateDataOverTimes($lavstData);

        /**
         * @var integer $key
         * @var Graph $graph
         */
        foreach ($graphs as $key => &$graph) {
            $this->mapDataToGraph($graph, $dataOverTimes);
            $this->doDataTypeCalculations($graph, $dataOverTimes);
        }

        unset($graph);
    }

    private function requestLavstData()
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cacheIdentifier = 'lavstData';

        if (($lavstResponse = $cache->get($cacheIdentifier)) === false) {
            $jsonResponse = file_get_contents('https://lavst.azurewebsites.net/Corona/Verlauf/data.js');
            $lavstResponse = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $jsonResponse), false);
            $cache->set($cacheIdentifier, $lavstResponse, [], 82800);
        }

        return $lavstResponse;
    }

    private function generateDataOverTimes($lavstData)
    {
        $indicators = $lavstData->geographies[0]->themes[0]->indicators;

        $dataOverTimes = range(0, 14);

        // read data from response and set up $dataOverTimes
        foreach ($indicators as $indicator) {

            if ($indicator->id !== 'i2' && $indicator->id !== 'i3') {
                continue;
            }

            $date = new \DateTime();
            $dateString = $indicator->date;
            $dateFormat = 'd.m.Y';
            // fix 'bis 19.10.20'
            if (strpos($dateString, 'bis') === 0) {
                $dateString = str_replace('bis ', '', $dateString);
                $dateFormat = 'd.m.y';
            }
            $date = $date::createFromFormat($dateFormat, $dateString);
            $date = $date->getTimestamp() * 1000;

            foreach ($dataOverTimes as $featureIndex => $dataOverTime) {
                // init array
                $dataOverTimes[$featureIndex] = is_array($dataOverTimes[$featureIndex]) ? $dataOverTimes[$featureIndex] : [];
                $dataOverTimes[$featureIndex][$date] = is_array($dataOverTimes[$featureIndex][$date]) ? $dataOverTimes[$featureIndex][$date] : [];

                // landkreis or SA: get value for specific indicator
                $indicatorValue = $featureIndex === 14 ? (int)$indicator->comparisonValues[0] : (int)$indicator->values[$featureIndex];

                // is indicator for AnzahlFall
                if ($indicator->id === 'i2') {
                    // landkreis or SA
                    $dataOverTimes[$featureIndex][$date]['AnzahlFall'] = $indicatorValue;
                }

                // is indicator for Sum
                if ($indicator->id === 'i3') {
                    $dataOverTimes[$featureIndex][$date]['sum'] = $indicatorValue;
                }
            }
        }

        return $dataOverTimes;
    }

    private function mapDataToGraph(Graph $graph, array $dataOverTimes)
    {
        if ($graph->isState) {
            $graph->dataOverTime = $dataOverTimes[14];
        } else {
            $graph->dataOverTime = $dataOverTimes[$graph->IdLandkreisLavst];
        }
    }

    private function doDataTypeCalculations(Graph $graph, array $dataOverTimes)
    {
        // get population
        $graph->population = RkiClientUtility::requestPopulationForGraph($graph);

        // sort
        ksort($graph->dataOverTime);

        foreach ($graph->dataOverTime as $key => &$day) {
            $day['avg'] = RkiClientUtility::calc7DayAverage($key, $graph->dataOverTime);
            $day['week'] = RkiClientUtility::calc7DayWeek($key, $graph->dataOverTime, $graph->population);
            $day['sumPer100k'] = RkiClientUtility::calcSumPer100k($key, $graph->dataOverTime, $graph->population);
        }
    }
}
