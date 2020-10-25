<?php

namespace Blueways\BwCovidNumbers\Utility;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

class ChartUtility
{

    /**
     * @var array
     */
    public $settings;

    /**
     * @var array
     */
    public $labels;

    public function __construct($settings)
    {
        $this->settings = $settings;
        $this->labels = [];
    }

    public function getChartConfig()
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cacheIdentifier = 'chartConfig' . md5(serialize($this->settings));

        if (($chartConfig = $cache->get($cacheIdentifier)) === false) {
            $chartConfig = $this->constructChartConfig();
            $cache->set($cacheIdentifier, $chartConfig, [], 82800);
        }

        return $chartConfig;
    }

    public function constructChartConfig()
    {
        $datasets = $this->getChartDataSets();
        $labels = $this->labels;
        $options = $this->settings['chartOptions'];

        return [
            'type' => 'bar',
            'data' => [
                'datasets' => $datasets,
                'labels' => $labels
            ],
            'options' => $options
        ];
    }

    public function getChartDataSets()
    {
        if (empty($this->settings['graphs'])) {
            return [];
        }

        $datasets = [];
        foreach ($this->settings['graphs'] as $tca) {
            $datasets[] = $this->getDatasetForTcaItem($tca);
        }

        return $datasets;
    }

    private function getDatasetForTcaItem($tca)
    {
        $where = $this->getWhereStatementFromTcaItem($tca);
        $dataOverTime = RkiClientUtility::getTransformedData($where);

        // add labels once
        if (empty($this->labels)) {
            foreach ($dataOverTime as $key => $day) {
                $date = date('d.m.', $key / 1000);
                $this->labels[] = $date;
            }
        }

        // cut data
        if ((int)$this->settings['filterTime'] > 0) {
            $offset = count($dataOverTime) - (int)$this->settings['filterTime'];
            $dataOverTime = array_slice($dataOverTime, $offset);
            // cut labels once
            if (count($this->labels) !== (int)$this->settings['filterTime']) {
                $this->labels = array_slice($this->labels, $offset);
            }
        }

        // fill in data
        $data = [];
        $firstArrayKey = array_keys($tca)[0];
        $dataTypeMapping = [1 => 'AnzahlFall', 2 => 'avg', 3 => 'sum'];
        $dataOffset = $dataTypeMapping[$tca[$firstArrayKey]['dataType']];
        foreach ($dataOverTime as $key => $day) {
            $data[] = $day[$dataOffset];
        }

        // get settings for style
        $label = $this->guessDatasetLabelForTcaItem($tca);
        $hexColor = $tca[$firstArrayKey]['color'] !== '' ? $tca[$firstArrayKey]['color'] : '#000000';
        list($r, $g, $b) = sscanf($hexColor, "#%02x%02x%02x");
        $graphType = (int)$tca[$firstArrayKey]['graphType'] === 1 ? 'bar' : 'line';
        $backgroundColor = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $this->settings['datasetOptions'][$graphType]['backgroundColorOpacity'] . ')';
        $borderColor = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $this->settings['datasetOptions'][$graphType]['borderColorOpacity'] . ')';

        $dataset = [
            'data' => $data,
            'label' => $label,
            'type' => $graphType,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor
        ];

        // override with typoScript settings
        ArrayUtility::mergeRecursiveWithOverrule($dataset, $this->settings['datasetOptions'][$graphType]);

        // remove custom properties (not in chart.js config)
        unset($dataset['backgroundColorOpacity'], $dataset['borderColorOpacity']);

        return $dataset;
    }

    /**
     * Generate where statement from flexform settings
     *
     * @return string
     */
    private function getWhereStatementFromTcaItem($tca)
    {
        if (array_keys($tca)[0] === 'state') {
            return "IdBundesland='" . $tca['state']['IdBundesland'] . "'";
        }

        if (is_numeric($tca[0]['IdLandkreis'])) {
        if (is_numeric($tca['district']['IdLandkreis'])) {
            return "IdLandkreis='" . $tca['district']['IdLandkreis'] . "'";
        }

        return "Landkreis like '%" . $tca['district']['IdLandkreis'] . "%'";
    }

    private function guessDatasetLabelForTcaItem($tca)
    {
        $firstArrayKey = array_keys($tca)[0];
        $dataType = (int)$tca[$firstArrayKey]['dataType'];

        // 1. default label is data type
        $llService = $this->getLanguageService();
        $label = $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:flexform.dataType.' . $dataType);

        // 2. set specific label of Bundesland or City (in case only dataType 1 or 2, e.g. 7 cities in comparison)
        $otherDataTypesInGraph = count(array_filter($this->settings['graphs'],
                static function ($graph) use ($dataType) {
                    return (int)array_pop($graph)['dataType'] !== $dataType;
                })) > 0;

        if (!$otherDataTypesInGraph && count($this->settings['graphs']) > 1) {
            return $firstArrayKey === 'state' ? $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:state.' . $tca[$firstArrayKey]['IdBundesland']) : $tca[$firstArrayKey]['IdLandkreis'];
        }

        return $label;
    }

    /**
     * @return LanguageService
     */
    private function getLanguageService()
    {
        return $GLOBALS['LANG'] ?: GeneralUtility::makeInstance(LanguageService::class);
    }
}
