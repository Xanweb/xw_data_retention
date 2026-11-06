<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Batch\Batch;
use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;

final class TaskController extends AbstractController
{
    /**
     * Versions per queued job. Each job must finish well inside one web-worker request
     * (max_execution_time), even for pages with hundreds of blocks per version.
     */
    public const VERSIONS_PER_JOB = 10;

    public function getName(): string { return t('Delete old page versions'); }
    public function getDescription(): string { return t('Applies the configured page version count, age and protection rules.'); }
    public function getConsoleCommandName(): string { return 'xw-data-retention:delete-page-versions'; }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        $runID = app(RunStore::class)->create();
        $settings = app(Policy::class)->get();
        $batch = Batch::create(null, static function () use ($settings, $runID) {
            $candidates = app(Candidates::class);
            foreach ($candidates->pages($settings) as $pageID) {
                $first = true;
                $after = 0;
                while ($ids = $candidates->versions($pageID, $after, $settings)) {
                    $after = end($ids);
                    foreach (array_chunk($ids, self::VERSIONS_PER_JOB) as $chunk) {
                        yield new DeletePageVersionsCommand($pageID, $runID, $chunk, $first);
                        $first = false;
                    }
                }
            }
        });
        $runner = new TaskRunner($task, $batch, $input, t('Page version retention beginning...'));
        $runner->setRunID($runID);
        return $runner;
    }
}
