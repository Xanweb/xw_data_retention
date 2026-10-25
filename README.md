# Xanweb Data Retention

Set separate retention periods for stored Concrete CMS form submissions and, optionally, database logs. This add-on does not change site content or the Concrete CMS core.

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

The package includes English interface text and a German translation. It adds one dashboard page and one native task. Uninstalling the package removes its own components; it does not delete submission records or Concrete core objects.

## Support

Publisher: Xanweb GmbH. Support is available through [GitHub Issues](https://github.com/Xanweb/xw_data_retention/issues). This is a free add-on with no guaranteed response time. Record a database backup before enabling deletion, especially when choosing a period for database logs. Report a reproducible issue with the Concrete version, package version, relevant task output, and the selected periods. Do not send personal submission data with a support request.
