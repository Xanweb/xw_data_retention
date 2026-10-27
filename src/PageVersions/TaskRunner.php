<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Runner\BatchProcessTaskRunner;

final class TaskRunner extends BatchProcessTaskRunner
{
    private $runID;
    public function setRunID(string $runID): void { $this->runID = $runID; }
    public function getRunID(): string { return $this->runID; }
    public function getTaskRunnerHandler(): string { return TaskRunnerHandler::class; }
}
