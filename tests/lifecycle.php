<?php
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\Package\PackageService;
use Doctrine\ORM\EntityManagerInterface;
use Xw\DataRetention\PageVersions\Policy;

$db = app('database')->connection();
if (app('site')->getDefault()->getSiteName() !== 'Xanweb Page Version Retention Test'
    || !in_array($db->getDatabase(), ['xw_retention_pv_93', 'xw_retention_pv_95'], true)) { throw new RuntimeException('Disposable page-version test installation required.'); }
$mode = $args[0] ?? 'installed';
$assert = static function ($ok, $message) { if (!$ok) { throw new RuntimeException($message); } echo "PASS: $message\n"; };
$package = app(PackageService::class)->getByHandle('xw_data_retention');
$tasks = app(TaskService::class);
$em = app(EntityManagerInterface::class);
if ($mode === 'uninstalled') {
    $assert(!$package && !$tasks->getByHandle('xw_delete_expired_form_data') && !$tasks->getByHandle('xw_delete_old_page_versions'), 'Both package tasks are removed on uninstall');
    $assert(!(int) $db->fetchOne('SELECT COUNT(*) FROM Config WHERE configNamespace = ?', ['xw_data_retention']), 'No package configuration remains');
    $assert(!$db->createSchemaManager()->tablesExist(['XwDataRetentionPageVersionRuns']), 'Package run-counter table is removed');
    $before = json_decode(file_get_contents(DIR_BASE . '/page-version-before-uninstall.json'), true);
    $assert((int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions') === $before['versions'], 'Uninstall does not delete any page versions');
    $assert($db->fetchAllAssociative('SELECT * FROM Config WHERE configNamespace <> ? OR configNamespace IS NULL ORDER BY configNamespace, configGroup, configItem', ['xw_data_retention']) === $before['unrelated_config'], 'Uninstall leaves unrelated configuration unchanged');
    return 0;
}
$assert($package && $package->getPackageVersion() === '0.9.0', 'Package version remains unchanged at main version 0.9.0');
if ($mode === 'seed') {
    app(Xw\DataRetention\Policy::class)->save(['express' => '2', 'legacy' => '3', 'logs' => '6']);
    $assert(!$tasks->getByHandle('xw_delete_old_page_versions'), 'Original main baseline has no page-version task');
    return 0;
}
if ($mode === 'prepare-uninstall') {
    file_put_contents(DIR_BASE . '/page-version-before-uninstall.json', json_encode([
        'versions' => (int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions'),
        'unrelated_config' => $db->fetchAllAssociative('SELECT * FROM Config WHERE configNamespace <> ? OR configNamespace IS NULL ORDER BY configNamespace, configGroup, configItem', ['xw_data_retention']),
    ]));
    return 0;
}
$assert($tasks->getByHandle('xw_delete_expired_form_data') && $tasks->getByHandle(Policy::TASK_HANDLE), 'Both independent package tasks are installed');
$assert($db->createSchemaManager()->tablesExist(['XwDataRetentionPageVersionRuns']), 'Owned run-counter table exists');
$assert(app(Policy::class)->get() === ['enabled' => false, 'keep' => 10, 'months' => 0, 'include_stacks' => false], 'Page version defaults are disabled with keep=10, no age limit and no stacks');
$assert(!$em->getRepository(ScheduledTask::class)->findBy(['task' => $tasks->getByHandle(Policy::TASK_HANDLE)]), 'Disabled feature creates no page-version schedule');
if ($mode === 'upgraded') {
    $assert(app(Xw\DataRetention\Policy::class)->get() === ['express' => 2, 'legacy' => 3, 'logs' => 6], 'Same-version forced upgrade preserves original retention periods');
}
$messages = Gettext\Translations::fromPoFile(dirname(__DIR__) . '/translations/de_DE.po');
$assert(count($messages) >= 50, 'External German catalog parses with the Core gettext library');
return 0;
