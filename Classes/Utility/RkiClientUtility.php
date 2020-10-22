<?php

namespace Blueways\BwCovidNumbers\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class RkiClientUtility
{

    public static function getTransformedData($where)
    {
        $data = json_decode(self::getApiData($where), false);
        $features = $data->features;
        $dataOverTime = [];

        // create array with day as index
        foreach ($features as $key => $feature) {
            $dataOverTime[$feature->attributes->Meldedatum]['AnzahlFall'] += $feature->attributes->AnzahlFall;
        }

        ksort($dataOverTime);
        $previousReportIndex = -1;

        // calculations for every day (sum, average,..)
        foreach ($dataOverTime as $key => $day) {

            // abort for first day
            if ($previousReportIndex === -1) {
                $previousReportIndex = $key;
                $dataOverTime[$key]['sum'] = $day['AnzahlFall'];
                continue;
            }

            // sum with previous day
            $dataOverTime[$key]['sum'] = $dataOverTime[$previousReportIndex]['sum'] + $day['AnzahlFall'];

            // calculate 7 day average
            $keyMinus7Days = $key - 604800000;
            $featuresOfLast7Days = array_filter($dataOverTime,
                static function ($featureKey) use ($key, $keyMinus7Days) {
                    return $featureKey > $keyMinus7Days && $featureKey <= $key;
                }, ARRAY_FILTER_USE_KEY);
            $dataOverTime[$key]['avg'] = round((array_reduce($featuresOfLast7Days, static function ($a, $b) {
                    return $a + $b['AnzahlFall'];
                }) / 7), 1);

            $previousReportIndex = $key;
        }

        return $dataOverTime;
    }

    public static function getApiData($whereStatement)
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cacheIdentifier = 'rkiData' . md5($whereStatement);
        $where = urlencode($whereStatement);

        if (($apiData = $cache->get($cacheIdentifier)) === false) {
            $apiData = file_get_contents('https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/RKI_COVID19/FeatureServer/0/query?where=' . $where . '&objectIds=&time=&resultType=none&outFields=*&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&sqlFormat=none&f=pjson&token=');
            $cache->set($cacheIdentifier, $apiData, [], 82800);
        }

        return $apiData;
    }
}
