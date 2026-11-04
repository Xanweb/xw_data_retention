<?php

namespace Concrete\Package\XwDataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Xanweb\Module\Installer;
use Xanweb\Module\Uninstaller;
use Xw\DataRetention\Module\Module;
use Xw\DataRetention\Policy;

class Controller extends Package
{
    protected $pkgHandle = 'xw_data_retention';
    protected $pkgVersion = '0.9.3';
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
        Module::boot();
    }

    public function install()
    {
        $page = Page::getByPath('/dashboard/system/automation/data_retention');
        if ($page && !$page->isError()) {
            throw new \RuntimeException('The dashboard path for data retention already exists.');
        }
        $pkg = parent::install();
        $installer = new Installer($pkg);
        $installer->installTasks(Policy::TASK_HANDLE, \Xw\DataRetention\PageVersions\Policy::TASK_HANDLE);
        $installer->installSinglePage('/dashboard/system/automation/data_retention', t('Data Retention'));
        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->app->make(Policy::class)->initializeUploads();
        $installer = new Installer($this->getPackageEntity());
        $installer->installTasks(Policy::TASK_HANDLE, \Xw\DataRetention\PageVersions\Policy::TASK_HANDLE);
        $installer->installSinglePage('/dashboard/system/automation/data_retention', t('Data Retention'));
    }

    public function uninstall()
    {
        parent::uninstall();
        Uninstaller::dropTables('XwDataRetentionPageVersionRuns');
        // In Core 9.3.0 and 9.5.7, DatabaseLoader::clearNamespace() builds but
        // does not execute its DELETE query. Remove only this package's rows.
        $this->app->make('database')->connection()->delete('Config', [
            'configNamespace' => $this->getPackageHandle(),
        ]);
    }
}
