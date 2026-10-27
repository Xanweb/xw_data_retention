<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Collection\Version\Version;

final class Cleanup
{
    public function page(int $pageID): array
    {
        $settings = app(Policy::class)->get();
        $count = ['versions' => 0, 'pages' => 0];
        if (!$settings['enabled']) { return $count; }
        $candidates = app(Candidates::class);
        $page = Page::getByID($pageID, 'RECENT');
        if (!$page || $page->isError()) { return $count; }
        $db = app('database')->connection();
        $cache = app('cache/request');
        $enabled = $cache->isEnabled();
        // Bound memory to one version and its blocks, even outside Core's cache-disabled worker.
        $cache->disable();
        try {
            $after = 0;
            while ($ids = $candidates->versions($pageID, $after, $settings)) {
                $count['pages'] = 1;
                foreach ($ids as $versionID) {
                    $after = $versionID;
                    // Settings and all guards are reread immediately before each destructive API call.
                    $settings = app(Policy::class)->get();
                    if (!$candidates->eligible($pageID, $versionID, $settings)) { continue; }
                    try {
                        $version = Version::get($page, $versionID);
                        if (!$version || $version->isApproved() || $version->isMostRecent()) { continue; }
                        $version->delete();
                    } catch (\Throwable $error) {
                        try {
                            app(LoggerFactory::class)->createLogger('xw_data_retention')->warning(
                                t('Could not delete version %d of page %d: %s', $versionID, $pageID, $error->getMessage()));
                        } catch (\Throwable $loggingError) { error_log('xw_data_retention: ' . $error->getMessage()); }
                    } finally {
                        // An event may throw after Core has already deleted the version.
                        if (!$db->fetchOne('SELECT 1 FROM CollectionVersions WHERE cID = ? AND cvID = ?', [$pageID, $versionID])) { ++$count['versions']; }
                        unset($version);
                    }
                }
            }
        } finally {
            if ($enabled) { $cache->enable(); }
            $cache->delete('page/' . $pageID);
            $cache->delete('/Page/Collection/' . $pageID . '/Version');
        }
        return $count;
    }
}
