<?php

namespace Blueways\BwCovidNumbers\Controller;

use Blueways\BwCovidNumbers\Utility\ChartUtility;
use Blueways\BwCovidNumbers\Utility\RkiClientUtility;
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
        $this->includeChartAssets();

        $chartUtil = GeneralUtility::makeInstance(ChartUtility::class, $this->settings);

        $datasets = $chartUtil->getChartDataSets();

        $where = $this->getWhereStatement();
        $dataOverTime = $this->getTransformedData($where);

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

        // get unique id to display multiple elements on one page
        $uid = $this->configurationManager->getContentObject() ? $this->configurationManager->getContentObject()->data['uid'] : mt_rand(0,
            99999);

        // create global variables
        $js = '';
        $js .= 'const chartConfig' . $uid . ' = {};';
        $js .= 'chartConfig' . $uid . '.dataset1data = ' . json_encode($dataset1data) . ';';
        $js .= 'chartConfig' . $uid . '.dataset1label = "' . $dataset1label . '";';
        $js .= 'chartConfig' . $uid . '.dataset2data = ' . json_encode($dataset2data) . ';';
        $js .= 'chartConfig' . $uid . '.dataset2label = "' . $dataset2label . '";';
        $js .= 'chartConfig' . $uid . '.labels = ' . json_encode($labels) . ';';
        $js .= 'window.bwcovidnumbers = window.bwcovidnumbers || {}' . ';';
        $js .= 'window.bwcovidnumbers["c' . $uid . '"] = chartConfig' . $uid . ';';

        /** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRender */
        $pageRender = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
        $pageRender->addJsInlineCode('bwcovidnumbers' . $uid, $js, true, true);

        return '<canvas id="chart-' . $uid . '" width="400" height="150"></canvas>';
    }

    private function includeChartAssets()
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

    public function getTransformedData($where)
    {
        return RkiClientUtility::getTransformedData($where);
    }

    /**
     * @return LanguageService
     */
    private function getLanguageService()
    {
        return $GLOBALS['LANG'] ?: GeneralUtility::makeInstance(LanguageService::class);
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
}
