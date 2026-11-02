<?php
/** Run with c5:exec only on a disposable installation; creates and deletes fixtures. */
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Command\Task\Input\Input;
use Concrete\Core\Command\Task\Runner\Context\ContextFactory;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Express\Entry;
use Concrete\Core\Entity\File\File;
use Concrete\Core\Events\EventDispatcher;
use Concrete\Core\Express\Entry\Manager as EntryManager;
use Concrete\Core\File\Filesystem;
use Concrete\Core\File\Image\Thumbnail\Type\Type as ThumbnailType;
use Concrete\Core\File\Import\FileImporter;
use Concrete\Core\File\Import\ImportOptions;
use Concrete\Core\Page\Page;
use Doctrine\ORM\EntityManagerInterface;
use Xw\DataRetention\Cleanup;
use Xw\DataRetention\Policy;
use Xw\DataRetention\Uploads;

$db = app('database')->connection();
if (app('site')->getDefault()->getSiteName() !== 'Xanweb Retention Upload Test'
    || !in_array($db->getDatabase(), ['xw_retention_110_93', 'xw_retention_110_95'], true)) {
    throw new RuntimeException('Use only the isolated Xanweb Retention Upload Test databases.');
}
$_SERVER['REQUEST_METHOD'] = 'GET';
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) && strpos($file, '/xw_data_retention/') !== false) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
$checks = 0;
$assert = static function ($ok, $message) use (&$checks) {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks; echo "PASS: $message\n";
};
$em = app(EntityManagerInterface::class);
$policy = app(Policy::class);
$cleanup = app(Cleanup::class);
$uploads = app(Uploads::class);
$filesystem = app(Filesystem::class);
$root = $filesystem->getRootFolder();
$folder = $filesystem->addFolder($root, 'Retention ' . uniqid());
$other = $filesystem->addFolder($root, 'Other ' . uniqid());
$child = $filesystem->addFolder($folder, 'Child');
$folderID = (int) $folder->getTreeNodeID();
$base = ['express' => '1', 'legacy' => '0', 'logs' => '0', 'express_upload_folder_id' => (string) $folderID];
$enabled = $base + ['delete_express_uploads' => '1', 'confirm_uploads' => '1'];
$disabled = $base + ['delete_express_uploads' => '0'];
$assert(!$uploads->isAllowedFolder((int) $root->getTreeNodeID()), 'Root folder is rejected');
foreach ([
    array_replace($enabled, ['express' => '0']),
    array_replace($enabled, ['express_upload_folder_id' => '0']),
    array_replace($enabled, ['express_upload_folder_id' => (string) $root->getTreeNodeID()]),
    array_replace($enabled, ['express_upload_folder_id' => '999999999']),
    array_replace($enabled, ['express_upload_folder_id' => '-1']),
    array_replace($enabled, ['express_upload_folder_id' => '1.5']),
    array_replace($enabled, ['delete_express_uploads' => 'yes']),
    array_replace($enabled, ['delete_express_uploads' => []]),
    array_replace($enabled, ['confirm_uploads' => null]),
] as $invalid) {
    $before = $policy->get();
    try { $policy->save($invalid); $rejected = false; } catch (InvalidArgumentException $e) { $rejected = true; }
    $assert($rejected && $policy->get() === $before, 'Invalid upload settings are rejected before writing any configuration');
}
$policy->save($disabled);
$assert($policy->get()['express_upload_folder_id'] === $folderID && !$policy->get()['delete_express_uploads'], 'Folder ID is stored and uploaded file deletion remains off');
$policy->save(array_replace($disabled, ['express' => '0']));
$task = app(TaskService::class)->getByHandle(Policy::TASK_HANDLE);
$assert(!$em->getRepository(Concrete\Core\Entity\Command\ScheduledTask::class)->findBy(['task' => $task]), 'A folder ID alone does not enable the schedule');
$policy->save($disabled);
$handle = 'retention_' . uniqid();
$builder = app('express')->buildObject($handle, $handle . 's', 'Retention test');
$builder->addAttribute('image_file', 'Upload', 'upload');
$builder->addAttribute('image_file', 'Another upload', 'another_upload');
$builder->save();
$formBuilder = $builder->buildForm('Retention form');
$formBuilder->addFieldSet('Files')->addAttributeKeyControl('upload')->addAttributeKeyControl('another_upload');
$form = $formBuilder->save();
$entity = $builder->getEntity();
$home = Page::getByID(1, 'ACTIVE');
$block = $home->addBlock(BlockType::getByHandle('express_form'), 'Main', ['exFormID' => $form->getID(), 'formName' => 'Retention test']);
$foreignBuilder = app('express')->buildObject($handle . '_other', $handle . '_others', 'Unrelated Express object');
$foreignBuilder->addAttribute('image_file', 'Upload', 'upload');
$foreignEntity = $foreignBuilder->save();
$allIDs = [];
$path = tempnam(sys_get_temp_dir(), 'retention-image-');
$image = imagecreatetruecolor(800, 600); imagefill($image, 0, 0, imagecolorallocate($image, 31, 93, 151)); imagepng($image, $path);
$importer = app(FileImporter::class);
$makeFile = static function ($target, $versions = false, $skipThumbnails = false) use ($importer, $path, &$allIDs) {
    $options = app(ImportOptions::class)->setImportToFolder($target)->setSkipThumbnailGeneration($skipThumbnails);
    $version = $importer->importLocalFile($path, 'retention-' . uniqid() . '.png', $options);
    $file = $version->getFile();
    $allIDs[] = (int) $file->getFileID();
    if ($versions) {
        $version = $importer->importLocalFile($path, 'retention-new-' . uniqid() . '.png', app(ImportOptions::class)->setAddNewVersionTo($file));
    }
    app(Doctrine\ORM\EntityManagerInterface::class)->refresh($file);
    return $file;
};
$makeEntry = static function ($file, $old = true, $object = null, $second = null) use ($entity, $em) {
    $entry = app('express')->buildEntry($object ?: $entity)->setAttribute('upload', $file)->save();
    if ($second) { $entry->setAttribute('another_upload', $second); }
    $entry->setDateCreated(new DateTime($old ? '-2 years' : 'now'));
    $em->persist($entry); $em->flush(); $em->refresh($entry);
    return (int) $entry->getID();
};
$exists = static function ($id) use ($db) { return (bool) $db->fetchOne('SELECT 1 FROM Files WHERE fID = ?', [$id]); };
$entryExists = static function ($id) use ($db) { return (bool) $db->fetchOne('SELECT 1 FROM ExpressEntityEntries WHERE exEntryID = ?', [$id]); };
// Verify that the documented folder storage setting applies to moved form uploads.
$privatePath = sys_get_temp_dir() . '/retention-private-' . uniqid(); mkdir($privatePath);
$privateConfig = new Concrete\Core\File\StorageLocation\Configuration\LocalConfiguration();
$privateConfig->setRootPath($privatePath); $privateConfig->setWebRootRelativePath('');
$storageFactory = app(Concrete\Core\File\StorageLocation\StorageLocationFactory::class);
$privateLocation = $storageFactory->persist($storageFactory->create($privateConfig, 'Retention private test'));
$privateFolder = $filesystem->addFolder($root, 'Private ' . uniqid());
$stored = $makeFile($privateFolder); $originalLocation = $stored->getFileStorageLocationObject()->getID();
$privateFolder->setTreeNodeStorageLocation($privateLocation);
$assert((int) $stored->getFileStorageLocationObject()->getID() === (int) $originalLocation, 'Changing folder storage does not migrate existing files');
$moved = $makeFile($root); $moved->getFileNodeObject()->move($privateFolder);
$assert((int) $moved->getFileStorageLocationObject()->getID() === (int) $privateLocation->getID()
    && !$privateLocation->getConfigurationObject()->hasPublicURL(), 'Moving a form upload into a folder applies its non-public storage location');
$privateDisk = $privateLocation->getFileSystemObject(); $privateOriginal = $moved->getApprovedVersion()->getFileResource()->getPath();
$assert($privateDisk->has($privateOriginal), 'Moved upload physically exists in the private storage directory');

$off = $makeFile($folder); $offID = (int) $off->getFileID(); $offEntry = $makeEntry($off);
$assert($cleanup->preview()['uploaded_files'] === 0, 'Disabled preview never offers file deletion');
$count = $cleanup->run();
$assert($count['express'] === 1 && $count['uploaded_files'] === 0 && $exists($offID) && !$entryExists($offEntry), 'Disabled feature deletes an expired entry but preserves its file');
$policy->save($enabled);
$eligible = $makeFile($folder, true); $eligibleID = (int) $eligible->getFileID();
$makeEntry($eligible, true, null, $eligible);
$disk = $eligible->getFileStorageLocationObject()->getFileSystemObject();
$physical = []; $thumbnails = [];
foreach ($eligible->getVersionList() as $version) {
    $version->refreshThumbnails(true);
    $physical[] = $version->getFileResource()->getPath();
    foreach (ThumbnailType::getVersionList() as $type) {
        $thumbnail = $type->getFilePath($version);
        if ($disk->has($thumbnail)) { $thumbnails[] = $thumbnail; }
    }
}
$assert(count($physical) === 2 && count($thumbnails) > 0, 'Fixture contains two real file versions and generated thumbnails');
$outside = $makeFile($other); $outsideID = (int) $outside->getFileID(); $makeEntry($outside);
$unlinked = $makeFile($folder); $unlinkedID = (int) $unlinked->getFileID();
$shared = $makeFile($folder); $sharedID = (int) $shared->getFileID(); $makeEntry($shared); $currentID = $makeEntry($shared, false);
$nested = $makeFile($child); $nestedID = (int) $nested->getFileID(); $makeEntry($nested);
$foreign = $makeFile($folder); $foreignID = (int) $foreign->getFileID(); $makeEntry($foreign); $foreignEntryID = $makeEntry($foreign, true, $foreignEntity);
$sharedExpired = $makeFile($folder); $sharedExpiredID = (int) $sharedExpired->getFileID(); $makeEntry($sharedExpired); $makeEntry($sharedExpired);
$used = $makeFile($folder); $usedID = (int) $used->getFileID(); $makeEntry($used);
$imageBlock = $home->addBlock(BlockType::getByHandle('image'), 'Main', ['fID' => $usedID, 'altText' => 'Retention usage test']);
app(Concrete\Core\File\Tracker\UsageTracker::class)->track($imageBlock->getController());
$assert((bool) $em->getRepository(Concrete\Core\Entity\Statistics\UsageTracker\FileUsageRecord::class)->findOneBy(['file_id' => $usedID]), 'A real image block is recorded by the Core usage tracker');
$preview = $cleanup->preview();
$assert($preview['uploaded_files'] === 2, 'Preview counts distinct eligible files and excludes shared, foreign, used and nested files');
$assert($preview['unlinked_files'] === 2, 'Preview warns about direct files with no remaining Express entry');
$count = $cleanup->run();
$assert($count['express'] === 8 && $count['uploaded_files'] === 2, 'Run counts deleted entries and distinct uploaded files');
$assert(!$exists($eligibleID) && !$exists($sharedExpiredID), 'Eligible files and files shared only by expired entries are deleted');
$assert(!(int) $db->fetchOne('SELECT COUNT(*) FROM FileVersions WHERE fID = ?', [$eligibleID]), 'All versions are deleted through the Core file API');
$assert(!array_filter(array_merge($physical, $thumbnails), static function ($item) use ($disk) { return $disk->has($item); }), 'Physical originals and thumbnails of every version are removed');
$assert($exists($outsideID), 'Expired upload outside the authorized folder remains');
$assert($exists($unlinkedID) && $exists($offID), 'Unlinked and previously retained files are never swept');
$assert($exists($sharedID) && $entryExists($currentID), 'File referenced by a current Express entry remains');
$assert($exists($foreignID) && $entryExists($foreignEntryID), 'File referenced by an unrelated Express entity remains');
$assert($exists($nestedID), 'File in a child folder remains');
$assert($exists($usedID), 'File with recorded Core page/block usage remains');

// A file error and a veto must not interrupt following entries or leave a transaction open.
$failing = $makeFile($folder); $failingID = (int) $failing->getFileID(); $failingEntry = $makeEntry($failing);
$veto = $makeFile($folder); $vetoID = (int) $veto->getFileID(); $makeEntry($veto);
$after = $makeFile($folder); $afterID = (int) $after->getFileID(); $makeEntry($after);
$events = app(EventDispatcher::class);
$listener = static function ($event) use ($failingID, $vetoID) {
    $id = (int) $event->getFileObject()->getFileID();
    if ($id === $failingID) { throw new RuntimeException('Simulated upload deletion error'); }
    if ($id === $vetoID) { $event->cancelDelete(); }
};
$events->addListener('on_file_delete', $listener);
try { $count = $cleanup->run(); } finally { $events->removeListener('on_file_delete', $listener); }
$assert($count['express'] === 3 && $count['uploaded_files'] === 1 && $exists($failingID) && $exists($vetoID) && !$exists($afterID), 'File exception and event veto preserve affected files and allow the next deletion');
$assert(!$entryExists($failingEntry) && $db->getTransactionNestingLevel() === 0, 'Entries stay deleted and Core veto transactions are closed');
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM Logs WHERE channel = ? AND message LIKE ?', ['xw_data_retention', '%Simulated upload deletion error%']) > 0, 'File errors are recorded on the package Concrete log channel');

$assert((int) $db->fetchOne('SELECT COUNT(*) FROM Logs WHERE channel = ? AND message LIKE ?', ['xw_data_retention', '%Core event prevented deletion%']) > 0, 'Core event vetoes are logged outside the rolled-back transaction');

// Deleting the authorized folder must not delete submissions prematurely or abort cleanup.
$missingFolder = $filesystem->addFolder($root, 'Removed ' . uniqid());
$missing = $makeFile($missingFolder); $missingID = (int) $missing->getFileID(); $missingEntry = $makeEntry($missing);
$policy->save(array_replace($enabled, ['express_upload_folder_id' => (string) $missingFolder->getTreeNodeID()]));
$missing->getFileNodeObject()->move($other);
$missingFolder->delete();
$assert($cleanup->preview()['upload_folder_missing'] && $cleanup->preview()['uploaded_files'] === 0, 'Removed folder produces a warning and a zero file preview');
$count = $cleanup->run();
$assert($count['express'] === 1 && $count['uploaded_files'] === 0 && !$entryExists($missingEntry) && $exists($missingID), 'Removed folder skips file deletion and continues entry cleanup');
$policy->save($enabled);

// A deadline reached during one file must not split an entry from its other files.
$atomicA = $makeFile($folder); $atomicAID = (int) $atomicA->getFileID();
$atomicB = $makeFile($folder); $atomicBID = (int) $atomicB->getFileID();
$atomicEntry = $makeEntry($atomicA, true, null, $atomicB);
$deferred = $makeFile($folder); $deferredID = (int) $deferred->getFileID(); $deferredEntry = $makeEntry($deferred);
$slow = static function ($event) use ($atomicAID) { if ((int) $event->getFileObject()->getFileID() === $atomicAID) { usleep(1500000); } };
$events->addListener('on_file_delete', $slow);
$oldLimit = ini_get('max_execution_time'); ini_set('max_execution_time', '2');
try { $count = $cleanup->run(); } finally { ini_set('max_execution_time', $oldLimit); $events->removeListener('on_file_delete', $slow); }
$assert($count['express'] === 1 && $count['uploaded_files'] === 2 && !$exists($atomicAID) && !$exists($atomicBID) && !$entryExists($atomicEntry), 'A deadline reached during file deletion still completes both files of that entry');
$assert($entryExists($deferredEntry) && $exists($deferredID), 'The next entry waits for the next run after the deadline');
$cleanup->run();

// Cross the 200-entry batch boundary with real files using the Dashboard task context.
$bulkIDs = [];
for ($i = 0; $i < 205; ++$i) {
    $file = $makeFile($folder, false, true); $bulkIDs[] = (int) $file->getFileID(); $makeEntry($file);
}
$assert($cleanup->preview()['uploaded_files'] === 205, 'Preview handles more than one batch of real uploads');
app('config')->set('concrete.processes.logging.method', 'file');
$logDirectory = DIR_FILES_UPLOADED_STANDARD . '/retention-process-logs/';
if (!is_dir($logDirectory)) { mkdir($logDirectory, 0777, true); }
app('config')->set('concrete.processes.logging.file.directory', $logDirectory);
$runner = $task->getController()->getTaskRunner($task, new Input());
$handler = app($runner->getTaskRunnerHandler());
$handler->boot($runner);
$context = app(ContextFactory::class)->createDashboardContext($runner);
$handler->start($runner, $context); $handler->run($runner, $context); $handler->complete($runner, $context);
// The Dashboard queues the process; consume it with the same Symfony Worker as Core.
$dispatcher = app(Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
$dispatcher->addSubscriber(new Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener(1));
$dispatcher->addSubscriber(new Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener(60));
$receiver = app(Concrete\Core\Messenger\Transport\TransportManager::class)->getReceivers()->get('async');
$worker = new Symfony\Component\Messenger\Worker(['async' => $receiver], app(Symfony\Component\Messenger\RoutableMessageBus::class), $dispatcher);
$worker->run();
$process = $runner->getProcess(); $em->refresh($process); $em->refresh($task);
$assert($process->getDateCompleted() && $process->getExitCode() === 0 && $task->getDateLastCompleted(), 'Dashboard task process completes successfully after 205 entries and files');
$log = app(Concrete\Core\Command\Process\Logger\LoggerFactoryInterface::class)->createFromProcess($process);
$assert($log && strpos(implode('\n', $log->readAsArray()), '205 Express entries (205 uploaded files)') !== false, 'Native task log includes the entry and uploaded file totals');
$assert($em->contains($task) && $em->contains($process), 'Task runner objects remain attached to Doctrine');
$assert(!array_filter($bulkIDs, $exists), 'All 205 bulk uploads are removed');
$assert($cleanup->preview()['express'] === 0, 'All expired scoped entries are processed across batches');

// Clean up only this suite's surviving fixtures, through Core APIs.
$imageBlock->deleteBlock(); $block->deleteBlock();
foreach ([$currentID, $foreignEntryID] as $id) { $entry = $em->find(Entry::class, $id); if ($entry) { app(EntryManager::class)->deleteEntry($entry); } }
foreach ($allIDs as $id) { if ($exists($id)) { $file = $em->find(File::class, $id); if ($file) { $file->delete(); } } }
$child->delete(); $folder->delete(); $other->delete(); $privateFolder->delete(); unlink($path);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($privatePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
rmdir($privatePath);
$policy->save(['express' => '0', 'legacy' => '0', 'logs' => '0']);
echo "COMPLETED: $checks upload checks\n";
return 0;
