<?php

namespace Blueways\BwCovidNumbers\Utility;

use Blueways\BwCovidNumbers\Domain\Model\Dto\Graph;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ChartUtility
{

    /**
     * @var array
     */
    public $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    public function getChartConfig()
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $uniqueLangString = LocalizationUtility::translate('bwcovidnumbers_pi1.title', 'bw_covid_numbers');
        $cacheIdentifier = 'chartConfig' . md5($uniqueLangString . serialize($this->settings));

        if (($chartConfig = $cache->get($cacheIdentifier)) === false) {
            $graphs = TcaToGraphUtility::createGraphsFromTca($this->settings);
            /** @var \Blueways\BwCovidNumbers\Utility\RkiClientUtility $rkiUtil */
            $rkiUtil = GeneralUtility::makeInstance(RkiClientUtility::class);
            $rkiUtil->updateGraphs($graphs);
            $chartConfig = $this->constructGraphConfig($graphs);

            $cache->set($cacheIdentifier, $chartConfig, [], 82800);
        }

        return $chartConfig;
    }

    public function constructGraphConfig($graphs)
    {
        $datasets = [];
        /** @var Graph $graph */
        foreach ($graphs as $graph) {
            $datasets[] = $graph->getDatasetConfig($this->settings);
        }

        $labels = $this->getAxeLabels($graphs);
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

    /**
     * @param $graphs
     * @return array|false[]|string[]
     */
    private function getAxeLabels($graphs)
    {
        if (!count($graphs)) {
            return [];
        }

        /** @var Graph $graph */
        $graph = $graphs[0];
        $offset = ((int)$this->settings['filterTime'] > 0) ? count($graph->dataOverTime) - (int)$this->settings['filterTime'] : 0;
        $dateKeys = array_slice(array_keys($graph->dataOverTime), $offset);

        return array_map(static function ($dateKey) {
            return date('d.m.', $dateKey / 1000);
        }, $dateKeys);
    }
}
