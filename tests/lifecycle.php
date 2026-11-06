<?php
/** Run only in the disposable combined release-test sites. */
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\Package\PackageService;
use Doctrine\ORM\EntityManagerInterface;
use Xw\DataRetention\Policy;
use Xw\DataRetention\PageVersions\Policy as VersionPolicy;

$db = app('database')->connection();
if (app('site')->getDefault()->getSiteName() !== 'Xanweb Retention Release Test'
    || !in_array($db->getDatabase(), ['xw_retention_release093_93', 'xw_retention_release093_95'], true)) {
    throw new RuntimeException('Disposable combined release test site and database required.');
}
$mode = $args[0] ?? 'defaults';
$assert = static function ($ok, $message) { if (!$ok) { throw new RuntimeException($message); } echo "PASS: $message\n"; };
$package = app(PackageService::class)->getByHandle('xw_data_retention');
$tasks = app(TaskService::class);
$em = app(EntityManagerInterface::class);
$schema = method_exists($db, 'createSchemaManager') ? $db->createSchemaManager() : $db->getSchemaManager();
if ($mode === 'cleanup') {
    foreach (app(Concrete\Core\File\StorageLocation\StorageLocationFactory::class)->fetchList() as $location) {
        if ($location->getName() === 'Retention private test' && !$location->hasFiles()) { $location->delete(); }
    }
    $assert(true, 'Empty test-only upload storage locations removed in a fresh Doctrine context');
    return 0;
}
if ($mode === 'uninstalled') {
    $assert(!$package && !$tasks->getByHandle('xw_delete_expired_form_data') && !$tasks->getByHandle('xw_delete_old_page_versions'), 'Uninstall removes package and both independent tasks');
    $assert(!(int) $db->fetchOne('SELECT COUNT(*) FROM Config WHERE configNamespace = ?', ['xw_data_retention']), 'Uninstall removes only the package configuration namespace');
    $assert(!$schema->tablesExist(['XwDataRetentionPageVersionRuns']), 'Uninstall removes the owned run-counter table');
    $before = json_decode(file_get_contents(DIR_BASE . '/retention-before-uninstall.json'), true);
    $assert((int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions v LEFT JOIN Pages p ON p.cID = v.cID WHERE p.pkgID <> ? OR p.pkgID IS NULL', [$before['package_id']]) === $before['versions'], 'Uninstall preserves versions outside the package-owned dashboard page');
    $assert($db->fetchAllAssociative('SELECT * FROM Config WHERE configNamespace <> ? OR configNamespace IS NULL ORDER BY configNamespace, configGroup, configItem', ['xw_data_retention']) === $before['config'], 'Uninstall preserves unrelated configuration');
    return 0;
}
if ($mode === 'seed') {
    $assert($package && $package->getPackageVersion() === '0.9.0', 'Upgrade baseline is actual main version 0.9.0');
    app(Policy::class)->save(['express' => '2', 'legacy' => '3', 'logs' => '6']);
    $assert(!$tasks->getByHandle('xw_delete_old_page_versions'), 'Baseline has no page-version task');
    $assert($package->getController()->getConfig()->get('retention.delete_express_uploads') === null, 'Baseline has no uploaded-file deletion option');
    return 0;
}
$assert($package && $package->getPackageVersion() === '0.9.4', 'Installed package version is 0.9.4');
if ($mode === 'prepare-uninstall') {
    file_put_contents(DIR_BASE . '/retention-before-uninstall.json', json_encode([
        'package_id' => $package->getPackageID(),
        'versions' => (int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions v LEFT JOIN Pages p ON p.cID = v.cID WHERE p.pkgID <> ? OR p.pkgID IS NULL', [$package->getPackageID()]),
        'config' => $db->fetchAllAssociative('SELECT * FROM Config WHERE configNamespace <> ? OR configNamespace IS NULL ORDER BY configNamespace, configGroup, configItem', ['xw_data_retention']),
    ]));
    return 0;
}
$formTask = $tasks->getByHandle('xw_delete_expired_form_data'); $versionTask = $tasks->getByHandle('xw_delete_old_page_versions');
$assert($formTask && $versionTask, 'Both native tasks are installed');
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM Tasks WHERE handle IN (?, ?)', ['xw_delete_expired_form_data', 'xw_delete_old_page_versions']) === 2, 'Task handles have no duplicates');
$assert($schema->tablesExist(['XwDataRetentionPageVersionRuns']), 'Page-version run-counter table exists');
$settings = app(Policy::class)->get();
$assert(!$settings['delete_express_uploads'] && $settings['express_upload_folder_id'] === 0, 'Express upload deletion starts disabled without an authorized folder');
$assert(app(VersionPolicy::class)->get() === ['enabled' => false, 'keep' => 10, 'months' => 0, 'include_stacks' => false], 'Page version cleanup and stack processing start disabled');
$assert(!$em->getRepository(ScheduledTask::class)->findBy(['task' => $versionTask]), 'Disabled page cleanup creates no schedule');
if ($mode === 'upgraded') {
    $assert($settings['express'] === 2 && $settings['legacy'] === 3 && $settings['logs'] === 6, 'Upgrade preserves original 2/3/6-month retention settings');
    $assert(count($em->getRepository(ScheduledTask::class)->findBy(['task' => $formTask])) === 1, 'Upgrade preserves the original form/log schedule without duplicates');
    $config = $package->getController()->getConfig();
    $assert($config->get('retention.delete_express_uploads') !== null && $config->get('retention.express_upload_folder_id') !== null, 'Upload defaults are initialized by the merged upgrade hook');
    app(Policy::class)->save(['express' => '0', 'legacy' => '0', 'logs' => '0']);
} else {
    $assert($settings['express'] === 0 && $settings['legacy'] === 0 && $settings['logs'] === 0, 'Fresh install leaves all form/log deletion disabled');
    $assert(!$em->getRepository(ScheduledTask::class)->findBy(['task' => $formTask]), 'Fresh install creates no form/log schedule');
}
$catalog = Gettext\Translations::fromPoFile(dirname(__DIR__) . '/translations/de_DE.po');
$messages = [];
foreach ($catalog as $message) { $messages[$message->getOriginal()] = $message->getTranslation(); }
$assert(($messages['Page versions'] ?? '') === 'Seitenversionen' && ($messages['Also delete uploaded files'] ?? '') !== '', 'Merged external German catalog includes both feature translations');
return 0;
