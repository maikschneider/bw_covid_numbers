<?php
defined('TYPO3_MODE') or die();

/***************
 * A add Pi1 Plugin
 */
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Blueways.BwCovidNumbers',
    'Pi1',
    'COVID-19 numbers',
    'bw_covid_numbers-icon'
);
// Add flexform for pi1
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['bwcovidnumbers_pi1'] = 'recursive,select_key,pages';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['bwcovidnumbers_pi1'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    'bwcovidnumbers_pi1',
    'FILE:EXT:bw_covid_numbers/Configuration/FlexForms/pi1.xml'
);
