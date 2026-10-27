<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<hr>
<section aria-labelledby="page-versions-title">
    <h2 id="page-versions-title"><?= t('Page versions') ?></h2>
    <?php if ($coreVersionTaskScheduled): ?>
    <div class="alert alert-warning" role="status">
        <?= t('The Core task Remove Old Page Versions is scheduled. It independently keeps only the 10 most recent versions, plus its protected versions. Disable its schedule when using this add-on to avoid conflicting retention rules.') ?>
        <a href="<?= h((string) \Concrete\Core\Support\Facade\Url::to('/dashboard/system/automation/tasks')) ?>"><?= t('View tasks') ?></a>
    </div>
    <?php endif ?>
    <form method="post" action="<?= $view->action('save_page_versions') ?>">
        <?php $token->output('save_page_version_retention') ?>
        <div class="mb-3">
            <label class="form-label" for="page_versions_enabled"><?= t('Automatic page version cleanup') ?></label>
            <select class="form-select" id="page_versions_enabled" name="page_versions_enabled">
                <option value="0"<?= $pageVersionSettings['enabled'] ? '' : ' selected' ?>><?= t('Never delete automatically') ?></option>
                <option value="1"<?= $pageVersionSettings['enabled'] ? ' selected' : '' ?>><?= t('Delete old page versions automatically') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="page_versions_keep"><?= t('Minimum versions to keep per page') ?></label>
            <input class="form-control" type="number" id="page_versions_keep" name="page_versions_keep" min="<?= \Xw\DataRetention\PageVersions\Policy::MIN_KEEP ?>" max="<?= \Xw\DataRetention\PageVersions\Policy::MAX_KEEP ?>" required value="<?= (int) $pageVersionSettings['keep'] ?>">
            <p class="form-text"><?= t('Always retain the newest N versions. Approved versions, future publication schedules and pages with an open workflow are additionally protected. The age limit applies to the version creation date.') ?></p>
        </div>
        <div class="mb-3">
            <label class="form-label" for="page_versions_months"><?= t('Additionally delete only versions older than') ?></label>
            <select class="form-select" id="page_versions_months" name="page_versions_months">
                <?php foreach (\Xw\DataRetention\PageVersions\Policy::MONTHS as $months): ?>
                <option value="<?= $months ?>"<?= $pageVersionSettings['months'] === $months ? ' selected' : '' ?>><?= $months === 0 ? t('No age limit') : t2('%d month', '%d months', $months) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="page_versions_include_stacks" name="page_versions_include_stacks"<?= $pageVersionSettings['include_stacks'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="page_versions_include_stacks"><?= t('Also clean up stacks and global areas') ?></label>
        </div>
        <p><?= t('Currently eligible: %d versions on %d pages.', $pageVersionPreview['versions'], $pageVersionPreview['pages']) ?></p>
        <p class="form-text"><?= t('The preview uses saved settings. Pages in the trash, drafts, dashboard and system pages, aliases and page templates are excluded. Stacks and global areas are processed only when explicitly enabled. The separate task runs daily at 03:40 when the scheduler is enabled.') ?></p>
        <p class="form-text"><?= t('Deleted page versions can only be restored from a backup.') ?></p>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="page_versions_confirm_activation" name="confirm_activation">
            <label class="form-check-label" for="page_versions_confirm_activation"><?= t('I confirm automatic deletion of the selected data types. Deletion cannot be undone.') ?></label>
        </div>
        <button class="btn btn-primary" type="submit"><?= t('Save page version settings') ?></button>
    </form>
</section>
