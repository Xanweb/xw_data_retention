# Xanweb Data Retention

Set separate retention periods for stored Concrete CMS form submissions and, optionally, database logs. This add-on preserves published page content and does not change the Concrete CMS core.

Compatible with Concrete CMS 9.3.0 and later. Tested with 9.3.0 and 9.5.7. No other add-on is required.

This free add-on is released under the MIT license; see `LICENSE.txt`.

## Install

1. Extract the ZIP so that `xw_data_retention/controller.php` is under your site's `packages/` directory.
2. Install **Xanweb Data Retention** in **Dashboard → Extend Concrete → Install**.
3. Open **Dashboard → System & Settings → Automation → Data Retention**.

Installation does not delete submissions or logs.

## Configure retention

Each data type starts at **Never delete automatically**:

| Data type | What is removed |
| --- | --- |
| Express Form submissions | Stored entries belonging to Express entities used by an installed Express Form block. Other Express objects are excluded. |
| Legacy Form block submissions | Stored answers and answer sets from the classic Form block. |
| Concrete CMS database logs | Entries in Concrete's `Logs` database table. This option is independent of form retention. |

Choose 1, 2, 3, 6, 12, 24, or 36 months per data type. Any nonzero period requires an explicit confirmation when saving. The dashboard shows the number of currently expired records. Each task run processes form submissions in 200-record batches until they are exhausted or the task time limit is reached (half of PHP's configured execution time, or five minutes when no limit is set). The task leaves the Concrete task runner's managed objects attached while deleting Express entries. Database logs are deleted in 5,000-record SQL batches until no expired entries remain. Deletion is permanent unless a prior backup is restored.

When at least one period is active, the add-on creates a native Concrete CMS scheduled task for 03:10 each day. Enable Concrete's scheduler and configure its server-side trigger under **Dashboard → System & Settings → Automation**. Merely saving a period does not run the task if the scheduler is disabled. The task can also be started manually from **Tasks**. Returning all periods to **Never delete automatically** removes the schedule; it does not restore previously deleted data.

## Scope and privacy

The add-on **does not delete uploaded files** from the File Manager. A file referenced by a submission can be reused elsewhere and needs a separate, usage-aware deletion process. Delivered notification emails in mailboxes, web server logs, external analytics and backups are outside its scope. When database-log retention is enabled, it also covers copies of sent emails stored in Concrete's `Logs` table. Express entries are included only while their entity is used by an existing Express Form block. A retention period is a technical setting, not a legal determination; align public privacy notices and external storage policies with the settings actually used.

The package includes English interface text. Translations are available through translate.concretecms.org or Dashboard → System & Settings → Multilingual → Update Languages. It adds one dashboard page and two independent native tasks. Uninstalling the package removes its own components; it does not delete submission records or Concrete core objects.

## Support

Publisher: Xanweb GmbH. Support is available through [GitHub Issues](https://github.com/Xanweb/xw_data_retention/issues). This is a free add-on with no guaranteed response time. Record a database backup before enabling deletion, especially when choosing a period for database logs. Report a reproducible issue with the Concrete version, package version, relevant task output, and the selected periods. Do not send personal submission data with a support request.

## Page versions (Unreleased)

The independent **Page versions** section starts at **Never delete automatically**. Enable it, choose the minimum number of versions to retain per page (**10–1000**) and optionally choose a creation-age limit of 1, 2, 3, 6, 12, 24 or 36 months. **No age limit** means that only the count and protection rules apply. Confirm deletion explicitly when saving. These settings do not change form/log retention or its 03:10 task.

A version is deleted only when it is outside the newest N versions **and**, if configured, older than the creation-age limit. The latest version and all approved versions are always retained, even if this exceeds N. A future publication start protects a version regardless of its approval flag. Pages with any open workflow are skipped entirely: Core stores the exact requested version in a serialized request, so this conservative rule also protects pending approvals without parsing serialized data in SQL.

Stacks and global areas are excluded unless **Also clean up stacks and global areas** is selected. The same rules then apply to each stack's history. Trash, composer drafts, dashboard/system pages, aliases and page templates remain excluded. Language pages are evaluated independently. The saved-settings preview counts eligible versions and distinct pages using SQL without loading version objects; changes before execution can reduce the actual count.

The separate native task **Delete old page versions** (`xw_delete_old_page_versions`) runs daily at **03:40** when the Concrete scheduler is enabled and can also be started manually under **Tasks**. Disabling this feature removes only its own schedule. It uses a native batch with one command per affected page; version IDs are fetched in groups of 100. Core's `Version::delete()` handles shared blocks and related version data. Per-version failures are logged on the `xw_data_retention` channel and later versions continue. Native process output includes each page's count and a final run total; enable Core process logging to retain the complete log in the Dashboard. Aggregate counters are stored in one package-owned table; completed counters older than 30 days are pruned. Uninstall removes this table, both package tasks, their schedules and package configuration, without running cleanup.

**Disable the schedule for Core's Remove Old Page Versions task when using this feature.** The Core task independently retains only the newest 10 plus its protected versions; it does not use this add-on's count/age settings or open-workflow guard. The dashboard warns if that Core task is scheduled. Running both tasks can remove history that the add-on would retain.

Deleted versions can only be recovered from a backup. Back up the database and site files before enabling cleanup. This feature does not delete current published page content.

The feature branch is based on `main` and deliberately keeps the existing package version. For an existing disposable test installation with the same version, apply its install/upgrade changes using `php concrete/bin/concrete c5:package:update --force xw_data_retention`. Release numbering, merging and tagging remain the publisher's decision. German source translations are in `translations/de_DE.po` outside the archive; publication through translate.concretecms.org is still required for new strings to reach Dashboard language updates.
