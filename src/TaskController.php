<?php

namespace Xw\DataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\ProcessTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;

class TaskController extends AbstractController
{
    public function getName(): string
    {
        return t('Delete expired form data');
    }

    public function getDescription(): string
    {
        return t('Applies the retention periods configured in the dashboard to Express and legacy form submissions and, optionally, Concrete CMS database logs.');
    }

    public function getConsoleCommandName(): string
    {
        return 'xw-data-retention:delete-expired';
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new ProcessTaskRunner($task, new DeleteExpiredCommand(), $input, t('Checking expired records.'));
    }
}
