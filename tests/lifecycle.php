<?php
/** Run via c5:exec, only in the disposable upload-test installations. */
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\Package\PackageService;
use Doctrine\ORM\EntityManagerInterface;
use Xw\DataRetention\Policy;

$db = app('database')->connection();
if (app('site')->getDefault()->getSiteName() !== 'Xanweb Retention Upload Test'
    || !in_array($db->getDatabase(), ['xw_retention_110_93', 'xw_retention_110_95'], true)) {
    throw new RuntimeException('Use only isolated Retention Upload Test databases.');
}
$mode = $args[0] ?? 'defaults';
$assert = static function ($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
};
if ($mode === 'cleanup') {
    // A fresh process has no loaded File entities referencing the removed storage.
    foreach (app(Concrete\Core\File\StorageLocation\StorageLocationFactory::class)->fetchList() as $location) {
        if ($location->getName() === 'Retention private test' && !$location->hasFiles()) { $location->delete(); }
    }
    $assert(true, 'Empty test-only private storage locations removed in a fresh Doctrine context');
    return 0;
}
$package = app(PackageService::class)->getByHandle('xw_data_retention');
$task = app(TaskService::class)->getByHandle('xw_delete_expired_form_data');
if ($mode === 'uninstalled') {
    $assert(!$package && !$task, 'Uninstall removes only the package and its task');
    $assert(!(int) $db->fetchOne('SELECT COUNT(*) FROM Config WHERE configNamespace = ?', ['xw_data_retention']), 'Uninstall removes its configuration namespace');
    return 0;
}
$assert($package && $task, 'Package and native task are installed');
if ($mode === 'seed-100') {
    $assert($package->getPackageVersion() === '1.0.0', 'Baseline is previous main code staged as version 1.0.0');
    app(Policy::class)->save(['express' => '2', 'legacy' => '3', 'logs' => '6']);
    $assert($package->getController()->getConfig()->get('retention.delete_express_uploads') === null, 'Old baseline has no uploaded file option');
    return 0;
}
$assert($package->getPackageVersion() === '1.1.0', 'Package version is 1.1.0');
$settings = app(Policy::class)->get();
if ($mode === 'upgraded') {
    $assert($settings['express'] === 2 && $settings['legacy'] === 3 && $settings['logs'] === 6, 'Upgrade preserves all previous retention periods');
    $assert($package->getController()->getConfig()->get('retention.delete_express_uploads') !== null
        && !$settings['delete_express_uploads'] && $settings['express_upload_folder_id'] === 0
        && $package->getController()->getConfig()->get('retention.express_upload_folder_id') !== null, 'Upgrade initializes uploaded file deletion off with an empty folder');
    $assert(count(app(EntityManagerInterface::class)->getRepository(ScheduledTask::class)->findBy(['task' => $task])) === 1, 'Upgrade preserves the existing daily task without duplicates');
} else {
    $assert($settings === ['express' => 0, 'legacy' => 0, 'logs' => 0, 'delete_express_uploads' => false, 'express_upload_folder_id' => 0], 'Fresh install leaves all deletion disabled');
    $assert(!app(EntityManagerInterface::class)->getRepository(ScheduledTask::class)->findBy(['task' => $task]), 'Fresh install creates no deletion schedule');
}
$messages = Gettext\Translations::fromPoFile(dirname(__DIR__) . '/translations/de_DE.po');
$assert(count($messages) > 40, 'External German catalog parses using the Core gettext library');
return 0;
