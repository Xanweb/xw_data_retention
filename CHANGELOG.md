# Changelog

## 0.9.4 — 2026-11-06

- Fix the invalid asset group notice on the Data Retention dashboard. The upload folder selector uses the tree assets already provided by the Concrete CMS dashboard.

## 0.9.3 — 2026-11-02

- Add optional deletion of uploads attached through file attributes to expiring Express Form entries, restricted to direct files in an explicitly authorized non-root folder.
- Require a positive Express retention period and a separate permanent file-deletion confirmation. Keep file deletion disabled after installation and upgrade from 0.9.0.
- Preserve files referenced by any remaining Express entry or recorded by the Core page/block usage tracker. Exclude subfolders, unrelated folders and unlinked files.
- Preview distinct eligible files and warn about unlinked files or a removed folder. Continue entry cleanup when the folder is missing.
- Delete through the Core API, including all versions and thumbnails. Log file errors and event vetoes on the package channel, continue cleanup and include the deleted file count in task output.
- Finish an entry and its files together before rechecking the time limit. Keep the task runner's Doctrine objects attached.
- Document protected storage per folder and the limits of usage tracking; extend the external German translation source.
- Add independent old-page-version retention with a configurable 10–1000 minimum, optional creation-age limit and SQL preview of versions and distinct pages.
- Preserve latest, approved and future-scheduled versions. Skip all versions on pages with open workflows, as well as trash, drafts, system pages, aliases and templates.
- Add optional stack/global-area cleanup using the same rules.
- Add a separately scheduled native batch task at 03:40, per-version error logging and aggregate run totals. Delete only through the Core version API.
- Warn when the conflicting Core Remove Old Page Versions task is scheduled.

## 0.9.0

- Initial Marketplace review build with independent Express Form, legacy Form and database-log retention periods, previews and a native scheduled task.
