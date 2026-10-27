<?php
defined('C5_EXECUTE') or die('Access Denied.');
/** @var array $settings */
/** @var array $choices */
/** @var array $preview */
/** @var bool $schedulerEnabled */
$labels = \Xw\DataRetention\Policy::labels();
?>
<div class="ccm-dashboard-header-buttons">
    <a class="btn btn-secondary" href="<?= h((string) \Concrete\Core\Support\Facade\Url::to('/dashboard/system/automation/tasks')) ?>"><?= t('View tasks') ?></a>
</div>
<h1><?= t('Data Retention') ?></h1>
<p><?= t('Nothing is deleted by default. Choose a retention period for each data type and confirm activation. The task runs daily at 03:10 when the Concrete CMS scheduler is enabled on the server.') ?></p>
<?php if (!$schedulerEnabled): ?>
<div class="alert alert-warning" role="status"><?= t('The Concrete CMS scheduler is currently disabled. Saved retention periods do not run automatically until the scheduler is enabled under Dashboard → System & Settings → Automation.') ?></div>
<?php endif ?>
<form method="post" action="<?= $view->action('save') ?>">
    <?php $token->output('save_data_retention') ?>
    <?php foreach ($labels as $source => $label): ?>
    <div class="mb-3">
        <label class="form-label" for="<?= h($source) ?>_months"><?= h($label) ?></label>
        <select class="form-select" id="<?= h($source) ?>_months" name="<?= h($source) ?>_months">
            <?php foreach ($choices as $months): ?>
            <option value="<?= (int) $months ?>"<?= (int) $settings[$source] === $months ? ' selected' : '' ?>><?= $months === 0 ? t('Never delete automatically') : t2('%d month', '%d months', $months) ?></option>
            <?php endforeach ?>
        </select>
        <p class="form-text"><?= t('Currently expired: %d records.', (int) $preview[$source]) ?></p>
    </div>
    <?php endforeach ?>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="confirm_activation" name="confirm_activation">
        <label class="form-check-label" for="confirm_activation"><?= t('I confirm automatic deletion of the selected data types. Deletion cannot be undone.') ?></label>
    </div>
    <button class="btn btn-primary" type="submit"><?= t('Save settings') ?></button>
</form>
<?php $view->element('page_versions/settings', compact('pageVersionSettings', 'pageVersionPreview', 'coreVersionTaskScheduled', 'view', 'token'), 'xw_data_retention'); ?>
<p class="form-text"><?= t('Form submissions are processed in batches until complete or the task time limit is reached. Database logs are processed in batches until complete.') ?></p>
<hr>
<p><?= t('These periods apply only to stored Express Form block submissions, legacy Form block submissions, and optional Concrete CMS database logs. Files in the File Manager, emails, web server logs, and backups are not deleted; they need separate retention rules.') ?></p>
