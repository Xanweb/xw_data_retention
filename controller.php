<?php

namespace Concrete\Package\XwDataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Xanweb\Module\Installer;
use Xanweb\Module\Uninstaller;
use Xw\DataRetention\Module\Module;
use Xw\DataRetention\PageVersions\Policy as PageVersionsPolicy;
use Xw\DataRetention\Policy;

class Controller extends Package
{
    private const DASHBOARD_PATH = '/dashboard/system/automation/data_retention';

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
        $page = Page::getByPath(self::DASHBOARD_PATH);
        if ($page && !$page->isError()) {
            throw new \RuntimeException('The dashboard path for data retention already exists.');
        }
        $pkg = parent::install();
        $this->installContent($pkg);
        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->app->make(Policy::class)->initializeUploads();
        $this->installContent($this->getPackageEntity());
    }

    private function installContent($pkg): void
    {
        $installer = new Installer($pkg);
        $installer->installTasks(Policy::TASK_HANDLE, PageVersionsPolicy::TASK_HANDLE);
        $installer->installSinglePage(self::DASHBOARD_PATH, t('Data Retention'));
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
