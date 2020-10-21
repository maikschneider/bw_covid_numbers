<?php

namespace Blueways\BwCovidNumbers\Controller;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Class CovidController
 *
 * @package Blueways\BwCovidNumbers\Controller
 */
class CovidController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    public function chartAction()
    {
        /** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRender */
        $pageRender = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
        if ($this->settings['chartsjs']['_typoScriptNodeValue']) {

            $pageRender->addJsFooterLibrary('chartjs',
                $this->getAssetPath($this->settings['chartsjs']['_typoScriptNodeValue']),
                'text/javascript',
                $this->settings['chartsjs']['compress'],
                $this->settings['chartsjs']['forceOnTop'], $this->settings['chartsjs']['allWrap'],
                $this->settings['chartsjs']['excludeFromConcatenation']);
        }

        if ($this->settings['chartsjsCss']['_typoScriptNodeValue']) {
            $pageRender->addCssFile(
                $this->getAssetPath($this->settings['chartsjsCss']['_typoScriptNodeValue']),
                'stylesheet',
                'all',
                'chartJs',
                $this->settings['chartsjsCss']['compress'],
                $this->settings['chartsjsCss']['forceOnTop'],
                $this->settings['chartsjsCss']['allWrap'],
                $this->settings['chartsjsCss']['excludeFromConcatenation']);
        }

        if ($this->settings['initChartJs']) {
            $pageRender->addJsFooterFile(
                $this->getAssetPath($this->settings['initChartJs']),
                'text/javascript');
        }

        $dataOverTime = $this->getTransformedData();

        // rename array keys
        foreach ($dataOverTime as $key => $day) {
            $date = date('d.m.', $key / 1000);
            $dataOverTime[$date] = $day;
            unset($dataOverTime[$key]);
        }

        // cut data
        if ((int)$this->settings['filterTime'] > 0) {
            $offset = count($dataOverTime) - (int)$this->settings['filterTime'];
            $dataOverTime = array_slice($dataOverTime, $offset);
        }

        // translation
        $llService = $this->getLanguageService();

        // create chart.js dataset & label
        $dataset1label = $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:chart.dataset1.label');
        $dataset1data = [];

        $dataset2label = $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:chart.dataset2.label');
        $dataset2data = [];

        $labels = [];

        // fill in data
        foreach ($dataOverTime as $key => $day) {
            $dataset1data[] = $day['AnzahlFall'];
            $dataset2data[] = $day['avg'];
            $labels[] = $key;
        }

        // create global variables
        $js = '';
        $js .= 'const bwcovidnumbers = {};';
        $js .= 'bwcovidnumbers.dataset1data = ' . json_encode($dataset1data) . ';';
        $js .= 'bwcovidnumbers.dataset1label = "' . $dataset1label . '";';
        $js .= 'bwcovidnumbers.dataset2data = ' . json_encode($dataset2data) . ';';
        $js .= 'bwcovidnumbers.dataset2label = "' . $dataset2label . '";';
        $js .= 'bwcovidnumbers.labels = ' . json_encode($labels) . ';';
        $js .= 'window.bwcovidnumbers = bwcovidnumbers' . ';';
        $js .= 'console.log(window.bwcovidnumbers);';

        $pageRender->addJsInlineCode('bwcovidnumbers', $js, true, true);

        return '<canvas id="myChart" width="400" height="150"></canvas>';
    }

    /**
     * @param $path
     * @return string
     */
    private function getAssetPath($path)
    {
        if (strpos($path, 'EXT:') === 0) {
            $parts = explode('/', $path);
            unset($parts[0]);
            $path = ExtensionManagementUtility::siteRelPath('bw_covid_numbers');
            $path .= implode('/', $parts);
        }

        return $path;
    }

    public function getTransformedData()
    {
        $where = $this->getWhereStatement();
        $data = json_decode($this->getApiData($where), false);
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

    /**
     * Generate where statement from flexform settings
     *
     * @return string
     */
    private function getWhereStatement()
    {
        if ((int)$this->settings['filterMode'] === 0) {
            return "IdBundesland='" . $this->settings['state'] . "'";
        }

        if (is_numeric($this->settings['district'])) {
            return "IdLandkreis='" . $this->settings['district'] . "'";
        }

        return "Landkreis like '%" . $this->settings['district'] . "%'";
    }

    private function getApiData($whereStatement)
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

    /**
     * @return LanguageService
     */
    private function getLanguageService()
    {
        return $GLOBALS['LANG'] ?: GeneralUtility::makeInstance(LanguageService::class);
    }
}
