<?php

namespace Blueways\BwCovidNumbers\Controller;

use stdClass;
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
            $pageRender->addJsFooterFile(
                $this->settings['chartsjs']['_typoScriptNodeValue'],
                'text/javascript',
                $this->settings['chartsjs']['compress'],
                $this->settings['chartsjs']['forceOnTop'], $this->settings['chartsjs']['allWrap'],
                $this->settings['chartsjs']['excludeFromConcatenation']);
        }

        if ($this->settings['chartsjsCss']['_typoScriptNodeValue']) {
            $pageRender->addCssFile(
                $this->settings['chartsjsCss']['_typoScriptNodeValue'],
                'stylesheet',
                'all',
                'chartJs',
                $this->settings['chartsjsCss']['compress'],
                $this->settings['chartsjsCss']['forceOnTop'],
                $this->settings['chartsjsCss']['allWrap'],
                $this->settings['chartsjsCss']['excludeFromConcatenation']);
        }

        $dataOverTime = $this->getTransformedData();

        // rename array keys
        foreach ($dataOverTime as $key => $day) {
            $date = new \DateTime();
            $date->setTimestamp(substr($key, 0, 10));
            $dataOverTime[date('d.m.', $key / 1000)] = $day;
            unset($dataOverTime[$key]);
        }

        // translation
        $llService = $this->getLanguageService();

        // create chart.js dataset & label
        $dataset1 = new stdClass();
        $dataset1->label = $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:chart.dataset1.label');
        $dataset1->data = [];

        $dataset2 = new stdClass();
        $dataset2->label = $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:chart.dataset1.label');
        $dataset2->data = [];

        $labels = [];

        foreach ($dataOverTime as $key => $day) {
            $dataset1->data[] = $day['AnzahlFall'];
            $dataset2->data[] = $day['avg'];
            $labels[] = $key;
        }

        // create global variables
        $js = '';
        $js .= 'const bwcovidnumbers = {};';
        $js .= 'bwcovidnumbers.dataset1 = ' . json_encode($dataset1) . ';';
        $js .= 'bwcovidnumbers.dataset2 = ' . json_encode($dataset2) . ';';
        $js .= 'bwcovidnumbers.labels = ' . json_encode($labels) . ';';
        $js .= 'window.bwcovidnumbers = bwcovidnumbers' . ';';
        $js .= 'console.log(window.bwcovidnumbers);';

        $pageRender->addJsInlineCode('bwcovidnumbers', $js, true, true);

        return '';
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

        return "Landkreis like ' % " . $this->settings['district'] . " % '";
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
