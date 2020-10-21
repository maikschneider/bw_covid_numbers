<?php

namespace Blueways\BwCovidNumbers\Task;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ClearCacheTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{

    /**
     * @return bool|void
     */
    public function execute()
    {
        /** @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend $cache */
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('bwcovidnumbers');
        $cache->flush();

        return true;
    }
}
