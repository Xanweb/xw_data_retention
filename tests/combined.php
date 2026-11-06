<?php
/** Cross-feature regression: run only in the disposable release-test sites. */
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\File\Filesystem;
use Doctrine\ORM\EntityManagerInterface;
use Xw\DataRetention\Policy;
use Xw\DataRetention\PageVersions\Policy as VersionPolicy;
$db = app('database')->connection();
if (app('site')->getDefault()->getSiteName() !== 'Xanweb Retention Release Test'
    || !in_array($db->getDatabase(), ['xw_retention_release093_93', 'xw_retention_release093_95'], true)) { throw new RuntimeException('Disposable combined test site and database required.'); }
$_SERVER['REQUEST_METHOD'] = 'GET';
$count = 0;
$assert = static function ($ok, $message) use (&$count) { if (!$ok) { throw new RuntimeException($message); } ++$count; echo "PASS: $message\n"; };
$forms = app(Policy::class); $versions = app(VersionPolicy::class);
$filesystem = app(Filesystem::class); $folder = $filesystem->addFolder($filesystem->getRootFolder(), 'Combined retention ' . uniqid());
$upload = ['express' => '1', 'legacy' => '0', 'logs' => '0', 'delete_express_uploads' => '1', 'express_upload_folder_id' => (string) $folder->getTreeNodeID(), 'confirm_uploads' => '1'];
$version = ['enabled' => '1', 'keep' => '20', 'months' => '12', 'include_stacks' => '1', 'confirm_activation' => '1'];
$forms->save($upload); $before = $forms->get(); $versions->save($version);
$assert($forms->get() === $before, 'Saving page-version policy preserves enabled upload settings and authorized folder');
$before = $versions->get(); $forms->save(array_replace($upload, ['logs' => '2']));
$assert($versions->get() === $before, 'Saving upload/form policy preserves all page-version settings');
$tasks = app(TaskService::class); $em = app(EntityManagerInterface::class);
$schedules = static function ($handle) use ($tasks, $em) { return $em->getRepository(ScheduledTask::class)->findBy(['task' => $tasks->getByHandle($handle)]); };
$assert(count($schedules(Policy::TASK_HANDLE)) === 1 && $schedules(Policy::TASK_HANDLE)[0]->getCronExpression() === '10 3 * * *', 'Combined settings retain the independent 03:10 form/upload schedule');
$assert(count($schedules(VersionPolicy::TASK_HANDLE)) === 1 && $schedules(VersionPolicy::TASK_HANDLE)[0]->getCronExpression() === '40 3 * * *', 'Combined settings retain the independent 03:40 page-version schedule');
$forms->save(array_replace($upload, ['express' => '0', 'delete_express_uploads' => '0']));
$assert(!$schedules(Policy::TASK_HANDLE) && count($schedules(VersionPolicy::TASK_HANDLE)) === 1 && $versions->get()['enabled'], 'Disabling form cleanup preserves active page-version cleanup');
$forms->save($upload); $versions->save(array_replace($version, ['enabled' => '0']));
$assert(count($schedules(Policy::TASK_HANDLE)) === 1 && !$schedules(VersionPolicy::TASK_HANDLE) && $forms->get()['delete_express_uploads'], 'Disabling page-version cleanup preserves active form/upload cleanup');
$versions->save($version);
Concrete\Core\User\User::loginByUserID(USER_SUPER_ID);
$page = Concrete\Core\Page\Page::getByPath('/dashboard/system/automation/data_retention');
Concrete\Core\Http\Request::getInstance()->setCurrentPage($page);
$assetNotice = '%ResponseAssetGroup::requireAsset%not a valid asset group handle%';
$assetNoticesBefore = (int) $db->fetchOne('SELECT COUNT(*) FROM Logs WHERE message LIKE ?', [$assetNotice]);
$controller = $page->getPageController(); $controller->on_start(); $controller->view(); $values = $controller->getSets();
$assert(isset($values['settings']['delete_express_uploads'], $values['pageVersionSettings']['keep'], $values['pageVersionPreview'], $values['coreVersionTaskScheduled']), 'Merged Dashboard controller supplies both feature datasets');
$view = $controller->getViewObject();
ob_start(); $html = (string) $view->render() . ob_get_clean();
$assert(substr_count($html, '<form ') === 2 && strpos($html, 'save_page_versions') !== false, 'Actual Dashboard template renders both independent forms');
$assert(strpos($html, 'name="express_upload_folder_id"') !== false && strpos($html, 'name="page_versions_keep"') !== false, 'Actual template renders the Core upload folder selector and page-version fields');
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM Logs WHERE message LIKE ?', [$assetNotice]) === $assetNoticesBefore,
    'The Data Retention dashboard adds no invalid asset group notice to the Core log');
$assert(strpos($html, 'name="confirm_uploads"') !== false && substr_count($html, 'name="confirm_activation"') === 2, 'Each merged form retains its required deletion confirmations');
$forms->save(['express' => '0', 'legacy' => '0', 'logs' => '0']);
$versions->save(array_replace($version, ['enabled' => '0', 'keep' => '10', 'months' => '0', 'include_stacks' => '0']));
$folder->delete();
$assert(!$schedules(Policy::TASK_HANDLE) && !$schedules(VersionPolicy::TASK_HANDLE), 'Turning both policies off removes both schedules independently');
echo "COMPLETED: $count combined checks\n";
return 0;
