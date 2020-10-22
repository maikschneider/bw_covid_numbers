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

        // get chart data
        /** @var ChartUtility $chartUtil */
        $chartUtil = GeneralUtility::makeInstance(ChartUtility::class, $this->settings);
        $chartConfig = $chartUtil->getChartConfig();

        // get unique id to display multiple elements on one page
        $uid = $this->configurationManager->getContentObject() ? $this->configurationManager->getContentObject()->data['uid'] : mt_rand(0,
            99999);

        // create global variables
        $js = 'window.bwcovidnumbers = window.bwcovidnumbers || {}' . ';';
        $js .= 'window.bwcovidnumbers["c' . $uid . '"] = ' . json_encode($chartConfig) .';';

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
}
