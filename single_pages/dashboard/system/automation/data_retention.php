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
    <?php if ($source === 'express'): ?>
    <fieldset class="mb-4" id="express-upload-settings">
        <legend><?= t('Uploaded files from Express forms') ?></legend>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="delete_express_uploads" name="delete_express_uploads"<?= $settings['delete_express_uploads'] ? ' checked' : '' ?><?= $settings['express'] > 0 ? '' : ' disabled' ?> aria-describedby="express-upload-help">
            <label class="form-check-label" for="delete_express_uploads"><?= t('Also delete uploaded files') ?></label>
        </div>
        <p class="form-text" id="express-upload-help"><?= t('Choose a positive Express retention period and a folder other than the File Manager root. Only files directly in this folder that belong to entries being deleted are eligible. Subfolders and files without an Express entry are excluded. Files still linked to another Express entry or recorded as used by a page or block are kept.') ?></p>
        <div class="mb-3">
            <label class="form-label"><?= t('Authorized upload folder') ?></label>
            <?= app(\Concrete\Core\Form\Service\Widget\FileFolderSelector::class)->selectFileFolder('express_upload_folder_id', $settings['express_upload_folder_id'], false) ?>
        </div>
        <p class="form-text"><?= t('Edit the Express Form block, open Options → Files → Add uploaded files to folder, and select this same folder. This does not move earlier uploads.') ?></p>
        <p class="form-text"><?= t('For personal or confidential uploads, configure a non-public storage location under Dashboard → System & Settings → Files → File Storage Locations. Use a local directory outside the web root without a public relative URL. Assign it to the folder using Edit Folder → Storage Location in the File Manager. New form uploads moved into that folder use its storage location. Changing the folder setting does not relocate existing files; change their storage location separately and restrict File Manager download permissions.') ?></p>
        <p class="form-text"><?= t('The Core usage tracker covers participating blocks and attributes. Hard-coded file URLs and custom integrations may not be recorded; use a dedicated upload folder and do not reuse its files elsewhere.') ?></p>
        <p><?= t('Currently eligible uploaded files: %d.', (int) $preview['uploaded_files']) ?></p>
        <p class="form-text"><?= t('The preview uses saved settings and current references. Time limits, deletion events, errors or concurrent changes may reduce the number deleted in the next run.') ?></p>
        <?php if ($preview['upload_folder_missing']): ?>
        <div class="alert alert-warning" role="status"><?= t('The authorized upload folder no longer exists. Uploaded file deletion will be skipped; expired entries will still be deleted.') ?></div>
        <?php endif ?>
        <?php if ($preview['unlinked_files'] > 0): ?>
        <div class="alert alert-warning" role="status"><?= t('%d files directly in the selected folder are not linked to any Express entry. The folder may also be used elsewhere. These files will not be deleted.', (int) $preview['unlinked_files']) ?></div>
        <?php endif ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="confirm_uploads" name="confirm_uploads">
            <label class="form-check-label" for="confirm_uploads"><?= t('I confirm that uploaded files in this folder that belong to expired Express entries will be permanently deleted.') ?></label>
        </div>
    </fieldset>
    <?php endif ?>
    <?php endforeach ?>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="confirm_activation" name="confirm_activation">
        <label class="form-check-label" for="confirm_activation"><?= t('I confirm automatic deletion of the selected data types. Deletion cannot be undone.') ?></label>
    </div>
    <button class="btn btn-primary" type="submit"><?= t('Save settings') ?></button>
</form>
<p class="form-text"><?= t('Form submissions are processed in batches until complete or the task time limit is reached. Database logs are processed in batches until complete.') ?></p>
<hr>
<p><?= t('These periods apply only to stored Express Form block submissions, legacy Form block submissions, and optional Concrete CMS database logs. File Manager uploads are kept by default; optional deletion applies only to files of expired Express entries directly in the authorized folder, subject to the reference checks above. Delivered emails, web server logs, and backups need separate retention rules.') ?></p>

<script>
$(function () {
    var period = document.getElementById('express_months');
    var toggle = document.getElementById('delete_express_uploads');
    var folder = document.querySelector('input[name="express_upload_folder_id"]');
    function updateUploadToggle() {
        toggle.disabled = Number(period.value) <= 0 || Number(folder.value) <= 0 || Number(folder.value) === <?= (int) app(\Concrete\Core\File\Filesystem::class)->getRootFolder()->getTreeNodeID() ?>;
        if (toggle.disabled) {
            toggle.checked = false;
        }
    }
    period.addEventListener('change', updateUploadToggle);
    // The Core tree selector writes its hidden input without dispatching change.
    var section = document.getElementById('express-upload-settings');
    ['click', 'keyup'].forEach(function (eventName) {
        section.addEventListener(eventName, function () {
            window.setTimeout(updateUploadToggle, 0);
        }, true);
    });
    updateUploadToggle();
});
</script>
