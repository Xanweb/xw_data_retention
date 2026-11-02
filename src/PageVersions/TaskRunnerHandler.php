<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Runner\BatchProcessTaskRunnerHandler;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;

final class TaskRunnerHandler extends BatchProcessTaskRunnerHandler
{
    public function boot(TaskRunnerInterface $runner)
    {
        parent::boot($runner);
        app(RunStore::class)->attach($runner->getRunID(), $runner->getProcess()->getID());
    }
}
