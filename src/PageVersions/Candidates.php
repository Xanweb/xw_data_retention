<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

/** Shared, read-only selection for preview, job enumeration and deletion rechecks. */
final class Candidates
{
    private function database() { return app('database')->connection(); }

    private function selection(array $settings): array
    {
        // The keep count is validated again even for internal callers: never interpolate request input.
        $offset = max(Policy::MIN_KEEP, min(Policy::MAX_KEEP, (int) $settings['keep'])) - 1;
        $scope = 'p.cIsDraft = 0 AND p.cIsTemplate = 0 AND p.cPointerID = 0
            AND (p.cPointerExternalLink IS NULL OR p.cPointerExternalLink = \'\')
            AND (p.cIsSystemPage = 0' . ($settings['include_stacks'] ? ' OR EXISTS (SELECT 1 FROM Stacks s WHERE s.cID = p.cID)' : '') . ')
            AND NOT EXISTS (SELECT 1 FROM PageWorkflowProgress pwp
                LEFT JOIN WorkflowProgress wp ON wp.wpID = pwp.wpID
                WHERE pwp.cID = p.cID AND (wp.wpIsCompleted IS NULL OR wp.wpIsCompleted <> 1))';
        $params = [];
        foreach (['/dashboard', app('config')->get('concrete.paths.trash', '/!trash'), app('config')->get('concrete.paths.drafts', '/!drafts')] as $path) {
            $path = rtrim($path, '/');
            $scope .= ' AND NOT EXISTS (SELECT 1 FROM PagePaths pp WHERE pp.cID = p.cID AND (pp.cPath = ? OR pp.cPath LIKE ? ESCAPE \'!\'))';
            $params[] = $path;
            $params[] = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path) . '/%';
        }
        $scope .= ' AND (v.cvIsApproved = 0 OR v.cvIsApproved IS NULL)
            AND (v.cvPublishDate IS NULL OR v.cvPublishDate <= ?)
            AND v.cvID < (SELECT newest.cvID FROM CollectionVersions newest
                WHERE newest.cID = p.cID ORDER BY newest.cvID DESC LIMIT 1 OFFSET ' . $offset . ')';
        $params[] = app('date')->getOverridableNow();
        if ((int) $settings['months'] > 0) {
            $scope .= ' AND v.cvDateCreated < ?';
            $params[] = app(\Xw\DataRetention\Policy::class)->cutoff((int) $settings['months'])->format('Y-m-d H:i:s');
        }
        return [$scope, $params];
    }

    public function preview(?array $settings = null): array
    {
        $settings = $settings ?? app(Policy::class)->get();
        if (!$settings['enabled']) { return ['versions' => 0, 'pages' => 0]; }
        [$where, $params] = $this->selection($settings);
        $row = $this->database()->fetchAssociative('SELECT COUNT(*) AS versions, COUNT(DISTINCT p.cID) AS pages
            FROM Pages p INNER JOIN CollectionVersions v ON v.cID = p.cID WHERE ' . $where, $params);
        return ['versions' => (int) $row['versions'], 'pages' => (int) $row['pages']];
    }

    public function pages(array $settings): \Generator
    {
        if (!$settings['enabled']) { return; }
        [$where, $params] = $this->selection($settings);
        $last = 0;
        do {
            $ids = $this->database()->fetchFirstColumn('SELECT DISTINCT p.cID FROM Pages p
                INNER JOIN CollectionVersions v ON v.cID = p.cID WHERE ' . $where . ' AND p.cID > ? ORDER BY p.cID LIMIT 200', array_merge($params, [$last]));
            foreach ($ids as $id) { $last = (int) $id; yield $last; }
        } while (count($ids) === 200);
    }

    public function versions(int $pageID, int $after, array $settings): array
    {
        if (!$settings['enabled']) { return []; }
        [$where, $params] = $this->selection($settings);
        return array_map('intval', $this->database()->fetchFirstColumn('SELECT v.cvID FROM Pages p
            INNER JOIN CollectionVersions v ON v.cID = p.cID WHERE ' . $where . ' AND p.cID = ? AND v.cvID > ? ORDER BY v.cvID LIMIT 100', array_merge($params, [$pageID, $after])));
    }

    public function eligible(int $pageID, int $versionID, array $settings): bool
    {
        if (!$settings['enabled']) { return false; }
        [$where, $params] = $this->selection($settings);
        return (bool) $this->database()->fetchOne('SELECT 1 FROM Pages p
            INNER JOIN CollectionVersions v ON v.cID = p.cID WHERE ' . $where . ' AND p.cID = ? AND v.cvID = ?', array_merge($params, [$pageID, $versionID]));
    }
}
