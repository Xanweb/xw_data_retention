# Marketplace submission — Xanweb Data Retention

**Package handle:** `xw_data_retention`
**First PRB upload:** `0.9.0`
**Minimum Concrete CMS version:** 9.3.0
**Upload feature target:** `1.1.0` (feature branch; merge and release pending)
**Upload feature tested versions:** 9.3.9 and 9.5.7; original retention also tested on 9.3.0
**License:** MIT (free add-on)

## Short description

Set separate automatic retention periods for stored Express Form submissions, classic Form block submissions and Concrete CMS database logs.

## Listing description

Xanweb Data Retention adds a dashboard settings page and a native scheduled task. Each data type starts at “Never delete automatically.” Administrators can choose 1, 2, 3, 6, 12, 24 or 36 months and must explicitly confirm activation. The page previews the number of expired records.

**Before enabling retention:** Deletion is permanent; create a database backup before activating any retention period. Retention periods are a technical setting, not a legal assessment; align them with your privacy policy. Uploaded files are kept by default. Optional Express upload deletion requires a positive Express retention period, an authorized non-root folder and a separate confirmation. Delivered emails, server logs and backups are not affected.

The task runs daily at 03:10 when Concrete's scheduler is enabled, and it can be run manually. Form responses are processed in 200-record batches until finished or the task time limit is reached; the running task's managed objects stay attached while Express entries are deleted. Database logs are deleted in 5,000-record SQL batches until finished. Returning every period to “Never” removes the schedule.

The package keeps File Manager files by default. Version 1.1.0 can delete files linked to an expired Express entry only when directly in the authorized folder; subfolders and unlinked files are excluded. Files still referenced by another Express entry or recorded as used by Core pages/blocks remain. Eligible files are deleted through Core with all versions and thumbnails. The preview includes distinct eligible files and folder warnings. Use a non-public storage location for confidential uploads; Core supports assigning storage locations per folder. Its usage tracker does not cover every hard-coded URL or custom integration. Delivered emails, mailboxes, web server logs, external analytics and backups remain outside scope. It does delete logged copies of sent mail inside Concrete's database `Logs` table when log retention is enabled. Express objects that are not used by an installed Express Form block remain untouched. The selected periods are technical settings, not legal advice.

Support: [GitHub Issues](https://github.com/Xanweb/xw_data_retention/issues). This is a free add-on with no guaranteed response time. Email is not a support channel.

## Features for 1.1.0

- Optional, separately confirmed upload deletion for expired Express Form entries only.
- Explicit authorization of one non-root File Manager folder; direct files only.
- Protection for remaining Express references and Core-recorded page/block usage.
- Distinct file preview, unlinked-file warning and missing-folder handling.
- Core file deletion includes all versions and thumbnails; file errors are logged and do not stop later entries.
- Upgrade from 1.0.x preserves retention periods and leaves file deletion disabled.

## Changelog — 1.1.0 (unreleased)

Add optional Express upload deletion with folder scoping, reference checks, previews, a separate confirmation and package-channel error logging. Existing retention settings remain unchanged on upgrade. Uploaded files remain untouched until explicitly enabled. See `docs/verification-1.1.0.md` for Core findings, tested versions and limitations. This branch must not be tagged or merged until the publisher approves the release.

## First PRB post (historical 0.9.0 submission)

Hello PRB,

This is the first submission of Xanweb Data Retention, version 0.9.0. The package adds three independent retention settings for stored Express Form responses, classic Form block responses, and Concrete CMS database logs. All settings default to “Never delete automatically.” Enabling deletion requires explicit confirmation. The dashboard previews expired counts and shows whether the native scheduler is enabled.

This is a free add-on released under the MIT license. Source code and issue tracker: https://github.com/Xanweb/xw_data_retention

To test it on a fresh Concrete CMS 9.3.0 or 9.5.7 site:

1. Install the package and confirm the dashboard page and native task appear. No records or schedule should be deleted or created by default.
2. Create an Express Form block, a classic Form block, and some test submissions. Add old and recent test entries and database log records in an isolated test site.
3. Set a short period for each data type, confirm activation, and check the preview and daily 03:10 schedule. Run `xw_delete_expired_form_data` manually. Old matching records should be deleted; recent records and unrelated Express objects should remain.
4. Set every period back to “Never” and confirm the schedule disappears. Uninstall and confirm the package page, task, schedule and package config are removed while submitted content remains.

The code uses Concrete CMS APIs and Doctrine DBAL already supplied by Core. It includes no third-party libraries. The Dashboard navigation cache is cleared by Core during install and upgrade; the package does not add a request-time session workaround.

One scoped uninstall workaround is intentional: `uninstall()` deletes the package’s rows from the `Config` table directly, because `DatabaseLoader::clearNamespace()` in Concrete 9.3.0 and 9.5.7 builds the DELETE query but never executes it. After Core removes the package's own page, task, and schedule, `uninstall()` deletes only `Config` rows whose namespace is `xw_data_retention`. Fresh-install uninstall tests on both versions confirmed that no package configuration remains and submission data is unaffected.

For any revised PRB upload I will increment the third version digit. The first approved release will be 1.0.0.
