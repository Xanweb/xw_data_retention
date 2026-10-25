<?php

namespace Concrete\Package\XwDataRetention\Controller\SinglePage\Dashboard\System\Automation;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Scheduler\Scheduler;
use Concrete\Core\Page\Controller\DashboardPageController;
use Xw\DataRetention\Cleanup;
use Xw\DataRetention\Policy;

class DataRetention extends DashboardPageController
{
    public function view(): void
    {
        $settings = $this->app->make(Policy::class)->get();
        $this->set('settings', $settings);
        $this->set('choices', Policy::MONTHS);
        $this->set('preview', $this->app->make(Cleanup::class)->preview($settings));
        $this->set('schedulerEnabled', $this->app->make(Scheduler::class)->isEnabled());
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
        try {
            if (max(array_map('intval', $values)) > 0 && !$this->request->request->get('confirm_activation')) {
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
