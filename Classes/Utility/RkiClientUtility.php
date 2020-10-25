<?php

namespace Blueways\BwCovidNumbers\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class RkiClientUtility
{

    public static function getTransformedData($tca)
    {
        $population = self::getPopulationFromTcaItem($tca);

        $where = self::getCovidWhereStatementFromTcaItem($tca);
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
            $dataOverTime[$key]['avg'] = self::calc7DayAverage($key, $dataOverTime);

            // calculate 7 per 100.000
            $dataOverTime[$key]['week'] = self::calc7DayWeek($key, $dataOverTime, $population);

            $previousReportIndex = $key;
        }

        return $dataOverTime;
    }

    public static function getPopulationFromTcaItem($tca)
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cacheIdentifier = 'populationData';
        //$cacheIdentifier .= array_keys($tca)[0] === 'state' ? 'state' . $tca['state']['IdBundesland'] : $tca['district']['IdBundIdLandkreisesland'];

        // try from cache
        if (($population = $cache->get($cacheIdentifier))) {
            return $population;
        }

        // get for state
        if (array_keys($tca)[0] === 'state') {
            $url = "https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/Coronaf%C3%A4lle_in_den_Bundesl%C3%A4ndern/FeatureServer/0/query?where=OBJECTID_1+%3D+11&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&returnCentroid=false&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=";
            $populationData = json_decode(file_get_contents($url));
            $population = $populationData->features[0]->attributes->LAN_ew_EWZ;
        }

        // get for district
        if (array_keys($tca)[0] !== 'state') {

            $where = "GEN+like+%27%25" . $tca['district']['IdLandkreis'] . "%25%27";

            if (is_numeric($tca['district']['IdLandkreis'])) {
                $where = "RS=" . $tca['district']['IdLandkreis'];
            }

            $url = "https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/RKI_Landkreisdaten/FeatureServer/0/query?where=" . $where . "&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&returnCentroid=false&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=";
            $populationData = json_decode(file_get_contents($url), true);
            $population = $populationData['features'][0]['attributes']['EWZ'];
        }

        $cache->set($cacheIdentifier, $population, [], 82800000);
        return $population;
    }

    /**
     * Generate where statement from flexform settings
     *
     * @return string
     */
    public static function getCovidWhereStatementFromTcaItem($tca)
    {
        if (array_keys($tca)[0] === 'state') {
            return "IdBundesland='" . $tca['state']['IdBundesland'] . "'";
        }

        if (is_numeric($tca['district']['IdLandkreis'])) {
            return "IdLandkreis='" . $tca['district']['IdLandkreis'] . "'";
        }

        return "Landkreis like '%" . $tca['district']['IdLandkreis'] . "%'";
    }

    public static function getApiData($whereStatement)
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cacheIdentifier = 'rkiData' . md5($whereStatement);
        $where = urlencode($whereStatement);

        if (($apiData = $cache->get($cacheIdentifier)) === false) {
            $apiData = file_get_contents('https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/RKI_COVID19/FeatureServer/0/query?where=' . $where . '&outStatistics=[{"statisticType":"sum","onStatisticField":"AnzahlFall","outStatisticFieldName":"AnzahlFall"}]&groupByFieldsForStatistics=Meldedatum&sqlFormat=none&f=pjson&token=');
            $cache->set($cacheIdentifier, $apiData, [], 82800);
        }

        return $apiData;
    }

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
        $keyMinus7Days = $date - 604800000;
        $featuresOfLast7Days = array_filter($dataOverTime,
            static function ($featureKey) use ($date, $keyMinus7Days) {
                return $featureKey > $keyMinus7Days && $featureKey <= $date;
            }, ARRAY_FILTER_USE_KEY);
        return round((array_reduce($featuresOfLast7Days, static function ($a, $b) {
                return $a + $b['AnzahlFall'];
            }) / ($population / 100000)), 1);
    }
}
