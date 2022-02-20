<?php

defined('TYPO3_MODE') || die('Access denied');

$_EXTKEY = 'bw_covid_numbers';

// register icon
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
    'bw_covid_numbers-icon',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:bw_covid_numbers/ext_icon.svg']
);

$typo3Version = TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionStringToArray(TYPO3\CMS\Core\Utility\VersionNumberUtility::getNumericTypo3Version());
$controllerName = $typo3Version['version_main'] >= 10 ? \Blueways\BwCovidNumbers\Controller\CovidController::class : 'Covid';

// register frontend plugin
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Blueways.BwCovidNumbers',
    'Pi1',
    [
        $controllerName => 'chart'
    ],
    []
);

// register caching frontend
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['bwcovidnumbers'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['bwcovidnumbers'] = array();
}

// register PageTS
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
    <INCLUDE_TYPOSCRIPT:source="FILE:EXT:bw_covid_numbers/Configuration/TSconfig/PageTS.typoscript">
');

// register cache cleaning task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Blueways\BwCovidNumbers\Task\ClearCacheTask::class] = array(
    'extension' => 'bw_covid_numbers',
    'title' => 'LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:scheduler.name',
    'description' => 'LLL:EXT:bw_covid_numbers/Resources/Private/Language/locallang.xlf:scheduler.description',
    'additionalFields' => ''
);

// register flexForm manipulation hook for v7
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['getFlexFormDSClass'][] = \Blueways\BwCovidNumbers\Hooks\FlexformManipulationHook::class;
