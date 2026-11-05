<?php

namespace Xw\DataRetention\Service;

use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Foundation\Service\Provider as BaseServiceProvider;
use Xw\DataRetention\PageVersions\Policy as PageVersionsPolicy;
use Xw\DataRetention\PageVersions\TaskController as PageVersionsTaskController;
use Xw\DataRetention\Policy;
use Xw\DataRetention\TaskController;

class Provider extends BaseServiceProvider
{
    public function register(): void
    {
        $taskManager = $this->app->make(TaskManager::class);

        $taskManager->extend(PageVersionsPolicy::TASK_HANDLE, static function ($app) {
            return $app->make(PageVersionsTaskController::class);
        });
        $taskManager->extend(Policy::TASK_HANDLE, static function ($app) {
            return $app->make(TaskController::class);
        });
    }
}
