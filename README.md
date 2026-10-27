# Xanweb Data Retention

Set separate retention periods for stored Concrete CMS form submissions and, optionally, database logs. This add-on does not change site content or the Concrete CMS core.

Compatible with Concrete CMS 9.3.0 and later. The upload feature is tested with 9.3.9 and 9.5.7; the original retention behavior was also tested with 9.3.0. No other add-on is required.

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

## Optional Express upload deletion (1.1.0)

Uploaded files are **kept by default**, including after an upgrade from 1.0.x. To enable deletion, choose a positive Express retention period, select a dedicated File Manager folder other than the root, enable **Also delete uploaded files**, and accept the separate permanent-deletion confirmation.

A file is eligible only when **both** conditions hold:

1. It is linked through an `image_file` file attribute to an Express entry being deleted because it expired.
2. It is directly inside the folder explicitly authorized in Data Retention. Subfolders are excluded.

Files with no Express entry are never swept. After deleting each expired entry through Core, the task also checks all remaining Express entries, including unrelated Express entities. A file linked to any remaining entry is kept. Files recorded as used by a page or block in Core's usage tracker are also kept. Core then deletes eligible files with all their versions and thumbnails. The task output includes the deleted file count. An entry and its files finish together before the time limit is checked again.

Edit the Express Form block and choose **Options → Files → Add uploaded files to folder**, selecting the same authorized folder. This controls new uploads; it does not move earlier uploads. The settings page previews distinct eligible files using saved settings and warns about files without Express references or a removed folder. The preview assumes all currently expired entries are processed; time limits, deletion events, errors and concurrent changes can reduce the next run's total. If the folder is removed, file deletion is skipped and expired entries are still deleted. A failed or vetoed file deletion is recorded under the `xw_data_retention` Concrete log channel and the task continues. Such a file becomes unlinked and is not automatically retried or swept later.

For personal or confidential uploads, create a **non-public storage location** under **Dashboard → System & Settings → Files → File Storage Locations**. For local storage, use a directory outside the web root with no public relative URL. In the File Manager, choose **Edit Folder → Storage Location** to assign it to the upload folder. New form uploads moved into this folder use its storage location. Changing the folder setting does not migrate existing files; change their storage location separately. Restrict download permissions as well: non-public storage prevents direct public URLs, but Concrete can still serve downloads according to its permissions.

Use a dedicated upload folder and avoid reusing these files elsewhere. The Core usage tracker covers participating blocks and attributes, but cannot prove that hard-coded URLs or custom integrations do not use a file. See [CHANGELOG.md](CHANGELOG.md) for the upgrade behavior. German translation source is maintained outside the distributable in `translations/de_DE.po`; language downloads require publication through translate.concretecms.org.

## Scope and privacy

The add-on **does not delete uploaded files by default**. Optional deletion applies only to expired Express entries and the explicitly authorized folder, with the reference checks described above. Legacy Form uploads and other File Manager files are excluded. Delivered notification emails in mailboxes, web server logs, external analytics and backups are outside its scope. When database-log retention is enabled, it also covers copies of sent emails stored in Concrete's `Logs` table. Express entries are included only while their entity is used by an existing Express Form block. A retention period is a technical setting, not a legal determination; align public privacy notices and external storage policies with the settings actually used.

The package includes English interface text. Translations are available through translate.concretecms.org or Dashboard → System & Settings → Multilingual → Update Languages. It adds one dashboard page and one native task. Uninstalling the package removes its own components; it does not delete submission records or Concrete core objects.

## Support

Publisher: Xanweb GmbH. Support is available through [GitHub Issues](https://github.com/Xanweb/xw_data_retention/issues). This is a free add-on with no guaranteed response time. Record a database backup before enabling deletion, especially when choosing a period for database logs. Report a reproducible issue with the Concrete version, package version, relevant task output, and the selected periods. Do not send personal submission data with a support request.
