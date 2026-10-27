<?php
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Command\Scheduler\Scheduler;
use Concrete\Core\Command\Task\Input\Input;
use Concrete\Core\Command\Task\Runner\Context\ContextFactory;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\Events\EventDispatcher;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Stack\Stack;
use Concrete\Core\Page\Template;
use Concrete\Core\Page\Type\Type;
use Concrete\Core\Page\Collection\Version\Version;
use Concrete\Core\Page\Collection\Version\VersionList;
use Concrete\Core\Workflow\EmptyWorkflow;
use Concrete\Core\Workflow\Progress\PageProgress;
use Concrete\Core\Workflow\Request\ApprovePageRequest;
use Doctrine\ORM\EntityManagerInterface;
use Xw\DataRetention\PageVersions\Candidates;
use Xw\DataRetention\PageVersions\Cleanup;
use Xw\DataRetention\PageVersions\Policy;
use Xw\DataRetention\PageVersions\RunStore;

$db = app('database')->connection();
if (app('site')->getDefault()->getSiteName() !== 'Xanweb Page Version Retention Test'
    || !in_array($db->getDatabase(), ['xw_retention_pv_93', 'xw_retention_pv_95'], true)) {
    throw new RuntimeException('Isolated page-version test installation required.');
}
$_SERVER['REQUEST_METHOD'] = 'GET';
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) && strpos($file, '/xw_data_retention/') !== false) { throw new ErrorException($message, 0, $severity, $file, $line); }
    return false;
});
$count = 0;
$assert = static function ($ok, $message) use (&$count) { if (!$ok) { throw new RuntimeException($message); } ++$count; echo "PASS: $message\n"; };
$policy = app(Policy::class); $cleaner = app(Cleanup::class); $candidates = app(Candidates::class);
$em = app(EntityManagerInterface::class); $site = app('site')->getDefault();
$home = Page::getByID(1, 'ACTIVE'); $template = Template::getByHandle('full');
$settings = ['enabled' => '1', 'keep' => '10', 'months' => '0', 'include_stacks' => '0', 'confirm_activation' => '1'];
$assert($policy->get() === ['enabled' => false, 'keep' => 10, 'months' => 0, 'include_stacks' => false], 'Fresh installation disables page version cleanup and stacks');
foreach (['9', '1001', '-1', '1.5', '10x', [], '999999999999999999999'] as $invalid) {
    $before = $policy->get();
    try { $policy->save(array_replace($settings, ['keep' => $invalid])); $rejected = false; } catch (InvalidArgumentException $e) { $rejected = true; }
    $assert($rejected && $policy->get() === $before, 'Invalid keep count is rejected before configuration changes');
}
try { $policy->save(array_replace($settings, ['confirm_activation' => null])); $rejected = false; } catch (InvalidArgumentException $e) { $rejected = true; }
$assert($rejected, 'Activation requires the existing explicit deletion confirmation');
$policy->save(array_replace($settings, ['keep' => '1000', 'enabled' => '0']));
$assert($policy->get()['keep'] === 1000, 'Maximum keep count is accepted');
$policy->save(array_replace($settings, ['enabled' => '0']));
$pages = [];
$make = static function ($name, $versions = 25, $parent = null) use (&$pages, $home, $template, $db) {
    $page = ($parent ?: $home)->add(Type::getByHandle('page'), ['uID' => 1, 'cName' => 'PV ' . $name . ' ' . uniqid(), 'pTemplateID' => $template->getPageTemplateID()]);
    $page->addBlock(BlockType::getByHandle('content'), 'Main', ['content' => '<p>Shared retained content</p>']);
    $page->getVersionObject()->approve(false);
    $cache = app('cache/request'); $wasEnabled = $cache->isEnabled(); $cache->disable();
    try {
        while ((int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions WHERE cID = ?', [$page->getCollectionID()]) < $versions) { $page = $page->cloneVersion('PV test'); }
    } finally { if ($wasEnabled) { $cache->enable(); } }
    $db->executeStatement('UPDATE CollectionVersions SET cvDateCreated = ? WHERE cID = ?', [(new DateTime('-2 years'))->format('Y-m-d H:i:s'), $page->getCollectionID()]);
    $pages[] = (int) $page->getCollectionID();
    return Page::getByID($page->getCollectionID(), 'RECENT');
};
$ids = static function ($page) use ($db) { return array_map('intval', $db->fetchFirstColumn('SELECT cvID FROM CollectionVersions WHERE cID = ? ORDER BY cvID', [$page->getCollectionID()])); };
$approveLatest = static function ($page) { Page::getByID($page->getCollectionID(), 'RECENT')->getVersionObject()->approve(false); };
$workflow = static function ($page, $versionID) {
    $request = new ApprovePageRequest();
    $request->setRequestedPage($page); $request->setRequestedVersionID($versionID); $request->setRequesterUserID(1); $request->save();
    return PageProgress::add(new EmptyWorkflow(), $request);
};
$off = $make('disabled'); $before = $ids($off);
$assert($candidates->preview() === ['versions' => 0, 'pages' => 0] && $cleaner->page($off->getCollectionID()) === ['versions' => 0, 'pages' => 0] && $ids($off) === $before, 'Disabled feature preserves all versions and reports zero candidates');
app(Xw\DataRetention\Policy::class)->save(['express' => '2', 'legacy' => '3', 'logs' => '6']);
$policy->save($settings);
$assert(app(Xw\DataRetention\Policy::class)->get() === ['express' => 2, 'legacy' => 3, 'logs' => 6], 'Page version settings do not change form or log retention');
$task = app(TaskService::class)->getByHandle(Policy::TASK_HANDLE);
$schedules = $em->getRepository(ScheduledTask::class)->findBy(['task' => $task]);
$assert(count($schedules) === 1 && $schedules[0]->getCronExpression() === '40 3 * * *', 'Own daily schedule runs at 03:40');
$policy->save($settings);
$assert(count($em->getRepository(ScheduledTask::class)->findBy(['task' => $task])) === 1, 'Saving again does not duplicate the schedule');
$approveLatest($off);
$previewSettings = $policy->get();
$sqlLogger = new Doctrine\DBAL\Logging\DebugStack();
$db->getConfiguration()->setSQLLogger($sqlLogger);
$preview = $candidates->preview($previewSettings);
$db->getConfiguration()->setSQLLogger(null);
$assert(count($sqlLogger->queries) === 1, 'Preview performs a single SQL count without hydrating version objects');
$assert($preview === ['versions' => 15, 'pages' => 1], 'SQL preview counts versions and distinct affected pages');
$result = $cleaner->page($off->getCollectionID());
$assert($result === ['versions' => 15, 'pages' => 1] && $ids($off) === range(16, 25), '25 versions with no age limit retain exactly the latest 10');
$oldApproved = $make('old approved'); $cleaner->page($oldApproved->getCollectionID());
$assert($ids($oldApproved) === array_merge([1], range(16, 25)), 'Older approved version survives above the 10-version minimum');
$small = $make('small', 6); $before = $ids($small); $cleaner->page($small->getCollectionID());
$assert($ids($small) === $before, 'Page with fewer than N versions remains unchanged');
$aged = $make('age'); $approveLatest($aged);
$db->executeStatement('UPDATE CollectionVersions SET cvDateCreated = ? WHERE cID = ? AND cvID BETWEEN 9 AND 15', [date('Y-m-d H:i:s'), $aged->getCollectionID()]);
$policy->save(array_replace($settings, ['months' => '12'])); $result = $cleaner->page($aged->getCollectionID());
$assert($result['versions'] === 8 && $ids($aged) === range(9, 25), 'Age limit combines with latest-N protection using version creation date');
$policy->save($settings);
$scheduled = $make('scheduled'); $approveLatest($scheduled);
Version::get($scheduled, 2)->setPublishDate((new DateTime('+1 year'))->format('Y-m-d H:i:s'));
$cleaner->page($scheduled->getCollectionID());
$assert($ids($scheduled) === array_merge([2], range(16, 25)), 'Future publication date protects even an older unapproved version');
$pending = $make('workflow'); $wp = $workflow($pending, 2); $before = $ids($pending);
$assert($wp->getWorkflowRequestObject()->getRequestedVersionID() === 2, 'Workflow fixture uses a real serialized Core approval request');
$cleaner->page($pending->getCollectionID());
$assert($ids($pending) === $before, 'Open workflow conservatively protects the entire page');
$wp->markCompleted(); $cleaner->page($pending->getCollectionID());
$assert(count($ids($pending)) === 11, 'Completed workflow no longer prevents cleanup; approved version remains');
$wp->delete();

$stack = Stack::addStack('PV stack ' . uniqid()); $global = Stack::addGlobalArea('PV global ' . uniqid());
foreach ([$stack, $global] as $item) {
    $item->getVersionObject()->approve(false);
    for ($i = 1; $i < 25; ++$i) { $item = $item->cloneVersion('PV stack test'); }
}
$assert(count($ids($stack)) === 25 && count($ids($global)) === 25, 'Stacks and global areas have real version histories');
$cleaner->page($stack->getCollectionID()); $cleaner->page($global->getCollectionID());
$assert(count($ids($stack)) === 25 && count($ids($global)) === 25, 'Stacks and global areas are excluded by default');
$policy->save(array_replace($settings, ['include_stacks' => '1']));
$cleaner->page($stack->getCollectionID()); $cleaner->page($global->getCollectionID());
$assert(count($ids($stack)) === 11 && count($ids($global)) === 11, 'Explicit stack option applies the same rules to stacks and global areas');
$trash = $make('trash'); $trash->moveToTrash(); $before = $ids($trash); $cleaner->page($trash->getCollectionID());
$assert($ids($trash) === $before, 'Trash page remains unchanged even when stacks are enabled');
$draft = $make('draft'); $draft->setPageToDraft(); $before = $ids($draft); $cleaner->page($draft->getCollectionID());
$assert($ids($draft) === $before, 'Composer draft remains unchanged');
$system = $make('system'); $system->move(Page::getByPath('/dashboard')); $before = $ids($system); $cleaner->page($system->getCollectionID());
$assert($ids($system) === $before, 'Dashboard/system page remains unchanged');

$localeService = app(Concrete\Core\Localization\Locale\Service::class);
$locale = null;
foreach ($site->getLocales() as $existingLocale) { if ($existingLocale->getLocale() === 'de_DE') { $locale = $existingLocale; break; } }
if (!$locale) { $locale = $localeService->add($site, 'de', 'DE'); $localeService->addHomePage($locale, $template, 'German', 'de'); }
$deHome = $locale->getSiteTree()->getSiteHomePageObject();
$en = $make('English'); $de = $make('Deutsch', 18, $deHome); $approveLatest($en); $approveLatest($de);
$cleaner->page($en->getCollectionID());
$assert(count($ids($en)) === 10 && count($ids($de)) === 18, 'Language pages are counted and processed independently');
$cleaner->page($de->getCollectionID()); $assert(count($ids($de)) === 10, 'Second language independently retains its newest 10');

$failure = $make('error'); $approveLatest($failure); $events = app(EventDispatcher::class);
$listener = static function ($event) use ($failure) { if ($event->getPageObject()->getCollectionID() === $failure->getCollectionID() && $event->getCollectionVersionObject()->getVersionID() === 2) { throw new RuntimeException('Simulated page version deletion error'); } };
$events->addListener('on_page_version_delete', $listener);
try { $result = $cleaner->page($failure->getCollectionID()); } finally { $events->removeListener('on_page_version_delete', $listener); }
$assert(count($ids($failure)) === 10 && $result['versions'] === 15, 'A version event error does not interrupt later versions; counts reflect actual deletion');
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM Logs WHERE channel = ? AND message LIKE ?', ['xw_data_retention', '%Simulated page version deletion error%']) > 0, 'Version failure is logged on the package channel');

$changing = $make('workflow during cleanup'); $approveLatest($changing); $changedWorkflow = null;
$onDelete = static function ($event) use ($changing, $workflow, &$changedWorkflow) {
    if ($event->getPageObject()->getCollectionID() === $changing->getCollectionID() && !$changedWorkflow) { $changedWorkflow = $workflow($changing, 2); }
};
$events->addListener('on_page_version_delete', $onDelete);
try { $result = $cleaner->page($changing->getCollectionID()); } finally { $events->removeListener('on_page_version_delete', $onDelete); }
$assert($result['versions'] === 1 && count($ids($changing)) === 24, 'Workflow opened during cleanup protects all remaining versions immediately');
$changedWorkflow->delete();

$large = $make('large', 225); $approveLatest($large);
$peak = memory_get_usage(true); $result = $cleaner->page($large->getCollectionID()); $growth = memory_get_usage(true) - $peak;
$assert($result['versions'] === 215 && count($ids($large)) === 10, '225-version page crosses multiple 100-ID chunks without skipping versions');
$assert($growth < 32 * 1024 * 1024, 'Version cleanup memory growth stays below 32 MiB for the multi-chunk fixture');
$assert(app('cache/request')->isEnabled(), 'Scoped request-cache state is restored after direct cleanup');

$coreTask = app(TaskService::class)->getByHandle('remove_old_page_versions');
app(Scheduler::class)->createScheduledTask($coreTask, new Input(), '0 4 * * *');
$assert($policy->coreTaskScheduled(), 'Scheduled Core cleanup task triggers the conflict warning');
foreach ($em->getRepository(ScheduledTask::class)->findBy(['task' => $coreTask]) as $schedule) { $em->remove($schedule); } $em->flush();
$assert(!$policy->coreTaskScheduled(), 'Warning disappears when Core task has no schedule');

foreach ($candidates->pages($policy->get()) as $pageID) { $cleaner->page($pageID); }
$assert($candidates->preview()['versions'] === 0, 'Prior fixtures leave no unprocessed eligible versions before the batch test');
$batchA = $make('batch A'); $batchB = $make('batch B'); $approveLatest($batchA); $approveLatest($batchB);
$policy->save($settings);
app('config')->set('concrete.processes.logging.method', 'file');
$logDirectory = DIR_FILES_UPLOADED_STANDARD . '/page-version-process-logs/'; if (!is_dir($logDirectory)) { mkdir($logDirectory, 0777, true); }
app('config')->set('concrete.processes.logging.file.directory', $logDirectory);
$runner = $task->getController()->getTaskRunner($task, new Input());
$assert($runner instanceof Concrete\Core\Command\Task\Runner\BatchProcessTaskRunner, 'Native batch runner queues one command per affected page');
$messages = iterator_to_array((function () use ($runner) { foreach ($runner->getBatch()->getMessages() as $message) { yield $message; } })());
$assert(count($messages) === 2 && count(array_unique(array_map(static function ($message) { return $message->pageID; }, $messages))) === 2, 'Batch contains exactly one command for each of the two affected pages');
$runner->getBatch()->setMessages($messages);
$handler = app($runner->getTaskRunnerHandler()); $handler->boot($runner);
$context = app(ContextFactory::class)->createDashboardContext($runner);
$handler->start($runner, $context); $handler->run($runner, $context); $handler->complete($runner, $context);
$dispatcher = app(Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
$dispatcher->addListener(Symfony\Component\Messenger\Event\WorkerRunningEvent::class, static function ($event) use ($db, $runner) {
    if ($db->fetchOne('SELECT dateCompleted FROM MessengerProcesses WHERE id = ?', [$runner->getProcess()->getID()])) { $event->getWorker()->stop(); }
});
$dispatcher->addSubscriber(new Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener(60));
$worker = new Symfony\Component\Messenger\Worker(['async' => app(Concrete\Core\Messenger\Transport\TransportManager::class)->getReceivers()->get('async')], app(Symfony\Component\Messenger\RoutableMessageBus::class), $dispatcher);
$worker->run();
$process = $runner->getProcess(); $em->refresh($process); $em->refresh($task);
$assert($process->getDateCompleted() && $process->getExitCode() === 0 && $task->getDateLastCompleted(), 'Dashboard batch process completes with successful task status');
$assert($em->contains($process) && $em->contains($task), 'Batch process and task remain managed by Doctrine');
$totals = app(RunStore::class)->totals($runner->getRunID());
$assert((int) $totals['versionsDeleted'] === 30 && (int) $totals['pagesProcessed'] === 2, 'Run counters aggregate deleted versions and processed pages');
$logger = app(Concrete\Core\Command\Process\Logger\LoggerFactoryInterface::class)->createFromProcess($process);
$assert(strpos(implode('\n', $logger->readAsArray()), 'deleted 30 versions and processed 2 pages') !== false, 'Final native task log contains the per-run totals');
$assert(count($ids($batchA)) === 10 && count($ids($batchB)) === 10, 'Both native batch jobs retain the configured version count');

// Core's editor VersionList and each remaining block must still resolve after cleanup.
foreach ([$off, $oldApproved, $changing, $aged, $scheduled, $pending, $en, $de, $failure, $large, $batchA, $batchB] as $page) {
    $page = Page::getByID($page->getCollectionID(), 'ACTIVE');
    $versions = (new VersionList($page))->get(100, 0);
    $assert(count($versions) === count($ids($page)), 'Editor version list loads every retained version');
    Concrete\Core\Http\Request::getInstance()->setCurrentPage($page);
    $frontendView = $page->getPageController()->getViewObject();
    $frontendView->setViewTheme($page->getCollectionThemeObject());
    ob_start(); $renderedPage = (string) $frontendView->render() . ob_get_clean();
    $assert(strpos($renderedPage, 'Shared retained content') !== false, 'Full frontend page renders retained content after cleanup');
    foreach ($versions as $version) {
        $loaded = Page::getByID($page->getCollectionID(), $version->getVersionID());
        foreach ($loaded->getBlocks('Main') as $block) { ob_start(); $block->display(); $html = ob_get_clean(); if (strpos($html, 'Shared retained content') === false) { throw new RuntimeException('Retained shared block failed to render'); } }
    }
}
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersionBlocks cvb JOIN Blocks b ON b.bID = cvb.bID WHERE cvb.cID = ?', [$off->getCollectionID()]) === 10, 'Shared blocks remain attached to all 10 retained versions');
$policy->save(array_replace($settings, ['enabled' => '0']));
$assert(!$em->getRepository(ScheduledTask::class)->findBy(['task' => $task]), 'Disabling cleanup removes only its own schedule');
$beforeDisabledRun = (int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions');
$idleRunner = $task->getController()->getTaskRunner($task, new Input());
$idleHandler = app($idleRunner->getTaskRunnerHandler()); $idleHandler->boot($idleRunner);
$idleContext = app(ContextFactory::class)->createDashboardContext($idleRunner);
$idleHandler->start($idleRunner, $idleContext); $idleHandler->run($idleRunner, $idleContext); $idleHandler->complete($idleRunner, $idleContext);
$em->refresh($idleRunner->getProcess());
$assert($idleRunner->getProcess()->getDateCompleted() && $idleRunner->getProcess()->getExitCode() === 0
    && (int) $db->fetchOne('SELECT COUNT(*) FROM CollectionVersions') === $beforeDisabledRun, 'Manual native task while disabled completes with zero deletions');

$assert(count($em->getRepository(ScheduledTask::class)->findBy(['task' => app(TaskService::class)->getByHandle(Xw\DataRetention\Policy::TASK_HANDLE)])) === 1, 'Original form/log task schedule stays unchanged');
app(Xw\DataRetention\Policy::class)->save(['express' => '0', 'legacy' => '0', 'logs' => '0']);
file_put_contents(DIR_BASE . '/page-version-fixtures.json', json_encode(['pages' => $pages, 'stacks' => [$stack->getCollectionID(), $global->getCollectionID()], 'browser_page_id' => $off->getCollectionID()]));
echo "COMPLETED: $count page version checks\n";
return 0;
