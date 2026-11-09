<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Collection\Version\Version;

final class Cleanup
{
    /**
     * @param int[]|null $versionIDs Versions to process (one queued chunk), or null for every eligible version.
     */
    public function page(int $pageID, ?array $versionIDs = null): array
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
        // Core's Block/Area lookups need the request cache: without it every block deletion
        // reloads all areas of the page (seconds per block on large pages). Memory stays bounded
        // to one version because the cache is flushed before and after each version.
        $cache->enable();
        $cache->flush();
        try {
            if ($versionIDs !== null) {
                if ($versionIDs) { $count['pages'] = 1; }
                foreach ($versionIDs as $versionID) {
                    $count['versions'] += $this->version($db, $cache, $candidates, $page, $pageID, (int) $versionID);
                }
            } else {
                $after = 0;
                while ($ids = $candidates->versions($pageID, $after, $settings)) {
                    $count['pages'] = 1;
                    foreach ($ids as $versionID) {
                        $after = $versionID;
                        $count['versions'] += $this->version($db, $cache, $candidates, $page, $pageID, $versionID);
                    }
                }
            }
        } finally {
            $cache->flush();
            if (!$enabled) { $cache->disable(); }
            $cache->delete('page/' . $pageID);
            $cache->delete('/Page/Collection/' . $pageID . '/Version');
        }
        return $count;
    }

    /** Deletes one version after rechecking every guard; returns 1 when the version no longer exists. */
    private function version($db, $cache, Candidates $candidates, Page $page, int $pageID, int $versionID): int
    {
        // Settings and all guards are reread immediately before each destructive API call.
        $settings = app(Policy::class)->get();
        if (!$candidates->eligible($pageID, $versionID, $settings)) { return 0; }
        try {
            $version = Version::get($page, $versionID);
            if (!$version || $version->isApproved() || $version->isMostRecent()) { return 0; }
            $version->delete();
        } catch (\Throwable $error) {
            try {
                app(LoggerFactory::class)->createLogger('xw_data_retention')->warning(
                    t('Could not delete version %d of page %d: %s', $versionID, $pageID, $error->getMessage()));
            } catch (\Throwable $loggingError) { error_log('xw_data_retention: ' . $error->getMessage()); }
        } finally {
            unset($version);
            $cache->flush();
        }
        // An event may throw after Core has already deleted the version.
        return $db->fetchOne('SELECT 1 FROM CollectionVersions WHERE cID = ? AND cvID = ?', [$pageID, $versionID]) ? 0 : 1;
    }
}
