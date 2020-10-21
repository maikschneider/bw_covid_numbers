<?php
defined('TYPO3_MODE') || die();

call_user_func(function () {

    // register TypoScript template
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'bw_covid_numbers',
        'Configuration/TypoScript',
        'COVID-19 numbers'
    );
});
