<?php

namespace Concrete\Package\XwDataRetention\Controller\SinglePage\Dashboard\System\Automation;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Scheduler\Scheduler;
use Concrete\Core\Page\Controller\DashboardPageController;
use Xw\DataRetention\Cleanup;
use Xw\DataRetention\Policy;

class DataRetention extends DashboardPageController
{
    private const TABS = ['retention', 'page-versions'];

    public function view(string $tab = ''): void
    {
        $this->set('activeTab', in_array($tab, self::TABS, true) ? $tab : 'retention');
        $settings = $this->app->make(Policy::class)->get();
        $this->set('settings', $settings);
        $this->set('choices', Policy::MONTHS);
        $this->set('preview', $this->app->make(Cleanup::class)->preview($settings));
        $this->requireAsset('core/file-manager');
        $versionPolicy = $this->app->make(\Xw\DataRetention\PageVersions\Policy::class);
        $this->set('pageVersionSettings', $versionPolicy->get());
        $this->set('pageVersionPreview', $this->app->make(\Xw\DataRetention\PageVersions\Candidates::class)->preview());
        $this->set('coreVersionTaskScheduled', $versionPolicy->coreTaskScheduled());
        $this->set('schedulerEnabled', $this->app->make(Scheduler::class)->isEnabled());
    }

    public function save_page_versions()
    {
        if (!$this->token->validate('save_page_version_retention')) {
            $this->error->add($this->token->getErrorMessage());
            $this->view('page-versions');
            return;
        }
        try {
            $this->app->make(\Xw\DataRetention\PageVersions\Policy::class)->save([
                'enabled' => $this->request->request->get('page_versions_enabled', '0'),
                'keep' => $this->request->request->get('page_versions_keep'),
                'months' => $this->request->request->get('page_versions_months'),
                'include_stacks' => $this->request->request->get('page_versions_include_stacks', '0'),
                'confirm_activation' => $this->request->request->get('confirm_activation'),
            ]);
            $this->flash('success', t('Page version retention settings saved.'));
            return $this->buildRedirect($this->action('view', 'page-versions'));
        } catch (\InvalidArgumentException $error) {
            $this->error->add($error->getMessage());
            $this->view('page-versions');
        }
    }

    public function save()
    {
        if (!$this->token->validate('save_data_retention')) {
            return $this->showError($this->token->getErrorMessage());
        }
        $values = [];
        foreach (Policy::SOURCES as $source) {
            $values[$source] = $this->request->request->get($source . '_months');
        }
        $values['delete_express_uploads'] = $this->request->request->get('delete_express_uploads', '0');
        $values['express_upload_folder_id'] = $this->request->request->get('express_upload_folder_id', '0');
        $values['confirm_uploads'] = $this->request->request->get('confirm_uploads');
        try {
            if (max(array_map(static function ($source) use ($values) { return (int) $values[$source]; }, Policy::SOURCES)) > 0 && !$this->request->request->get('confirm_activation')) {
                throw new \InvalidArgumentException(t('Please explicitly confirm automatic deletion.'));
            }
            $this->app->make(Policy::class)->save($values);
            $this->flash('success', t('Retention periods saved.'));
            return $this->buildRedirect($this->action('view'));
        } catch (\InvalidArgumentException $error) {
            $this->error->add($error->getMessage());
            $this->view();
        }
    }
}
