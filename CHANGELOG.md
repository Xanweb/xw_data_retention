# Changelog

## 1.1.0 — Unreleased

- Add optional deletion of uploads attached through file attributes to expiring Express Form entries, restricted to direct files in an explicitly authorized non-root folder.
- Require a positive Express retention period and a separate permanent file-deletion confirmation. Keep file deletion disabled after installation and upgrade from 1.0.x.
- Preserve files referenced by any remaining Express entry or recorded by the Core page/block usage tracker. Exclude subfolders, unrelated folders and unlinked files.
- Preview distinct eligible files and warn about unlinked files or a removed folder. Continue entry cleanup when the folder is missing.
- Delete through the Core API, including all versions and thumbnails. Log file errors and event vetoes on the package channel, continue cleanup and include the deleted file count in task output.
- Finish an entry and its files together before rechecking the time limit. Keep the task runner's Doctrine objects attached.
- Document protected storage per folder and the limits of usage tracking; extend the external German translation source.

The development branch is based on the repository's 0.9.0 main. A 1.0.0 baseline is staged from that prior code for upgrade verification; this changelog does not claim that 1.0.0 has been released.

## 0.9.0

- Initial Marketplace review build with independent Express Form, legacy Form and database-log retention periods, previews and a native scheduled task.
