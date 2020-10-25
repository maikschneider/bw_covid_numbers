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

        $datasets = $this->cutAndSyncDatasets($datasets);

        return $datasets;
    }

    private function getDatasetForTcaItem($tca)
    {
        $dataOverTime = RkiClientUtility::getTransformedData($tca);

        // fill in data
        $data = [];
        $firstArrayKey = array_keys($tca)[0];
        $dataTypeMapping = [1 => 'AnzahlFall', 2 => 'avg', 3 => 'sum', 4 => 'week'];
        $dataOffset = $dataTypeMapping[$tca[$firstArrayKey]['dataType']];
        foreach ($dataOverTime as $key => $day) {
            $data[$key] = $day[$dataOffset];
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

    private function cutAndSyncDatasets($datasets)
    {
        // gather all dates from all datasets
        $allPointsInTime = [];
        foreach ($datasets as $set) {
            $allPointsInTime = array_merge($allPointsInTime, array_keys($set['data']));
        }

        // fill empty values for new points in time with empty values
        foreach ($datasets as $key => $set) {

            foreach ($allPointsInTime as $pointInTime) {
                if (!array_key_exists($pointInTime, $set['data'])) {
                    $datasets[$key]['data'][$pointInTime] = null;
                }
            }

            ksort($datasets[$key]['data']);
        }

        // fill labels
        foreach (array_keys($datasets[0]['data']) as $date) {
            $date = date('d.m.', $date / 1000);
            $this->labels[] = $date;
        }

        // always cut data and labels (to reset the array index from timestamp to increasing numbers)
        $offset = ((int)$this->settings['filterTime'] > 0) ? count($this->labels) - (int)$this->settings['filterTime'] : 0;
        foreach ($datasets as $key => $set) {
            $datasets[$key]['data'] = array_slice($set['data'], $offset);
        }
        $this->labels = array_slice($this->labels, $offset);

        return $datasets;
    }
}
