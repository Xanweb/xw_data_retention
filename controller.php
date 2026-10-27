<?php

namespace Concrete\Package\XwDataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use Xw\DataRetention\Policy;
use Xw\DataRetention\TaskController;

class Controller extends Package
{
    protected $pkgHandle = 'xw_data_retention';
    protected $pkgVersion = '0.9.0';
    protected $appVersionRequired = '9.3.0';
    protected $pkgAutoloaderRegistries = ['src' => 'Xw\\DataRetention'];

    public function getPackageName()
    {
        return t('Xanweb Data Retention');
    }

    public function getPackageDescription()
    {
        return t('Configurable retention for form submissions, page versions and Concrete CMS database logs.');
    }

    public function on_start()
    {
        $this->app->make(TaskManager::class)->extend(\Xw\DataRetention\PageVersions\Policy::TASK_HANDLE, static function ($app) {
            return $app->make(\Xw\DataRetention\PageVersions\TaskController::class);
        });
        $this->app->make('director')->addListener('on_close_process', [$this->app->make(\Xw\DataRetention\PageVersions\RunStore::class), 'completed']);
        $this->app->make(TaskManager::class)->extend(Policy::TASK_HANDLE, static function ($app) {
            return $app->make(TaskController::class);
        });
    }

    public function install()
    {
        $page = Page::getByPath('/dashboard/system/automation/data_retention');
        if ($page && !$page->isError()) {
            throw new \RuntimeException('The dashboard path for data retention already exists.');
        }
        $pkg = parent::install();
        $tasks = $this->app->make(TaskService::class);
        if (!$tasks->getByHandle(Policy::TASK_HANDLE)) {
            $tasks->add(Policy::TASK_HANDLE, $pkg->getPackageEntity());
        }
        $this->installPageVersionTask($pkg->getPackageEntity());
        $page = SinglePage::add('/dashboard/system/automation/data_retention', $pkg);
        if (!$page || $page->isError()) {
            throw new \RuntimeException('Could not install the data retention dashboard page.');
        }
        $page->update(['cName' => t('Data Retention')]);
        return $pkg;
    }

    private function installPageVersionTask($entity): void
    {
        $tasks = $this->app->make(TaskService::class);
        if (!$tasks->getByHandle(\Xw\DataRetention\PageVersions\Policy::TASK_HANDLE)) {
            $tasks->add(\Xw\DataRetention\PageVersions\Policy::TASK_HANDLE, $entity);
        }
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installPageVersionTask($this->getPackageEntity());
    }

    public function uninstall()
    {
        parent::uninstall();
        $this->app->make('database')->connection()->executeStatement('DROP TABLE IF EXISTS XwDataRetentionPageVersionRuns');
        // In Core 9.3.0 and 9.5.7, DatabaseLoader::clearNamespace() builds but
        // does not execute its DELETE query. Remove only this package's rows.
        $this->app->make('database')->connection()->delete('Config', [
            'configNamespace' => $this->getPackageHandle(),
        ]);
    }
}
