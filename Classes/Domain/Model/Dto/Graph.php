<?php

namespace Blueways\BwCovidNumbers\Domain\Model\Dto;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

class Graph
{

    /**
     * @var integer
     */
    public $population;

    /**
     * @var array
     */
    public $dataOverTime;

    /**
     * @var boolean
     */
    public $isState;

    /**
     * @var integer
     */
    public $IdBundesland;

    /**
     * @var integer
     */
    public $IdLandkreis;

    /**
     * @var string
     */
    public $Landkreis;

    /**
     * @var integer
     */
    public $dataType;

    /**
     * @var string
     */
    public $color;

    /**
     * @var integer
     */
    public $graphType;

    public function getWhereStatementForCovidQuery()
    {
        if ($this->isState) {
            return "IdBundesland='" . $this->IdBundesland . "'";
        }

        if ($this->IdLandkreis) {
            return "IdLandkreis='" . $this->IdLandkreis . "'";
        }

        return "Landkreis like '%" . $this->Landkreis . "%'";
    }

    public function getCacheIdentifierForPopulation()
    {
        $prefix = 'populationData';

        if ($this->isState) {
            return $prefix . md5('state' . $this->IdBundesland);
        }

        if ($this->IdLandkreis) {
            return $prefix . md5('district' . $this->IdLandkreis);
        }

        return $prefix . md5('district' . $this->Landkreis);
    }

    public function getWhereStatementForPopulationQuery()
    {
        if ($this->isState) {
            return '';
        }

        $where = "GEN+like+%27%25" . $this->Landkreis . "%25%27";

        if ($this->IdLandkreis && is_numeric($this->IdLandkreis)) {
            $where = "RS=" . $this->IdLandkreis;
        }

        return $where;
    }

    /**
     * @param $settings
     * @return array
     */
    public function getDatasetConfig($settings): array
    {
        // select data from dataType
        $dataTypeMapping = [1 => 'AnzahlFall', 2 => 'avg', 3 => 'sum', 4 => 'week', 5 => 'sumPer100k'];
        $dataOffset = $dataTypeMapping[$this->dataType];
        $data = array_map(function ($day) use ($dataOffset) {
            return $day[$dataOffset];
        }, $this->dataOverTime);

        // cut
        $offset = ((int)$settings['filterTime'] > 0) ? count($data) - (int)$settings['filterTime'] : 0;
        $data = array_slice($data, $offset);

        // get settings for style
        $label = $this->guessLabelFromTcaSettings($settings);
        $hexColor = $this->color !== '' ? $this->color : '#000000';
        list($r, $g, $b) = sscanf($hexColor, "#%02x%02x%02x");
        $graphType = $this->graphType === 1 ? 'bar' : 'line';
        $backgroundColor = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $settings['datasetOptions'][$graphType]['backgroundColorOpacity'] . ')';
        $borderColor = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $settings['datasetOptions'][$graphType]['borderColorOpacity'] . ')';

        $dataset = [
            'data' => $data,
            'label' => $label,
            'type' => $graphType,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor
        ];

        // override with typoScript settings
        ArrayUtility::mergeRecursiveWithOverrule($dataset, $settings['datasetOptions'][$graphType]);

        // remove custom properties (not in chart.js config)
        unset($dataset['backgroundColorOpacity'], $dataset['borderColorOpacity']);

        return $dataset;
    }

    private function guessLabelFromTcaSettings($settings)
    {
        $dataType = $this->dataType;

        // 1. default label is data type
        $llService = $this->getLanguageService();
        $label = $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:flexform.dataType.' . $dataType);

        // 2. set specific label of Bundesland or City (in case only dataType 1 or 2, e.g. 7 cities in comparison)
        $otherDataTypesInGraph = count(array_filter($settings['graphs'],
                static function ($graph) use ($dataType) {
                    return (int)array_pop($graph)['dataType'] !== $dataType;
                })) > 0;

        if (!$otherDataTypesInGraph && count($settings['graphs']) > 1) {
            return $this->isState ? $llService->sL('LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:state.' . $this->IdBundesland) : $this->Landkreis;
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
