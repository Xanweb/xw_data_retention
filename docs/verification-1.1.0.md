# Verification — Express upload retention 1.1.0

Date: **2026-10-27**. Branch: `feature/delete-form-uploads`. Package version: **1.1.0**. This is an unreleased feature build. No merge, tag, Packagist release or Marketplace submission is part of this delivery.

## Result

All **180 upload assertions** passed across four Core/PHP combinations, plus upgrade, uninstall and fresh-install checks for each combination. Acceptance: **keine Meldungen aus dem Paket**. Upstream Core/vendor diagnostics remain visible; none originated from the package.

| Concrete CMS | PHP | Upload checks | Package diagnostics | Upstream suite diagnostics | Lifecycle |
| --- | --- | --- | --- | --- | --- |
| 9.3.9 | 8.2.35 | 45 / 45 | 0 | 3 | pass |
| 9.3.9 | 8.4.27 | 45 / 45 | 0 | 151 | pass |
| 9.5.7 | 8.2.35 | 45 / 45 | 0 | 3 | pass |
| 9.5.7 | 8.4.27 | 45 / 45 | 0 | 10 | pass |

Diagnostic numbers count repeated occurrences, not unique issues. The three PHP 8.2 occurrences come from Core's dynamic `FileLogger::$filePath` property when native process logging is exercised. PHP 8.4 also reports upstream nullable-signature/E_STRICT and other Core/vendor deprecations. Core files and diagnostic reporting were not modified to obtain passing results. Full output, with local workstation paths redacted, is in [test-results](test-results/); structured results are in [matrix.json](test-results/matrix.json).

The 9.3.x target used the official complete **9.3.9** release. The 9.5.7 target used the local, read-only Concrete Core reporting **9.5.7**. Both tests used fresh, disposable installations and separate private MySQL databases, with no TCP database listener. No customer data was involved. The new feature was not rerun on 9.3.0; compatibility statements distinguish the previously tested base behavior from this 9.3.9/9.5.7 feature matrix.

## Upgrade and default behavior

The repository's current `main` is `15f3486b963a8088f5a68a124cd307b9d23d2e83`, whose package controller still reports 0.9.0. For the requested 1.0.0 → 1.1.0 test, the baseline was made from that unchanged code with **only** `pkgVersion` staged as 1.0.0. This is a version-transition test, not a claim that a 1.0.0 release already exists.

Each matrix cell performed:

1. Install the staged 1.0.0 baseline; save Express/legacy/log periods of 2/3/6 months and confirm no new upload options exist.
2. Replace the package with the `git archive` 1.1.0 payload and run Core's package upgrade.
3. Verify that 2/3/6 remain unchanged, the new upload toggle is off, the folder is 0, and the original daily task exists once.
4. Run all 45 upload checks, including a queued Dashboard process with 205 real uploads.
5. Uninstall; verify that the package, native task and package config namespace are removed.
6. Reinstall 1.1.0; verify all periods remain disabled, uploaded file deletion is off, no folder is selected and no deletion schedule is created.

Concrete's database config repository stores false as an empty string and zero as a string. The lifecycle test checks both the persisted presence of the new keys and their normalized Policy values. Initialization only supplies missing options, preserving existing settings on later upgrades.

## Verified behavior

- Reject activation without a positive Express period, a valid non-root File Manager folder or the separate file-deletion confirmation. Invalid input writes no configuration. A folder ID alone cannot activate scheduling.
- With the feature off, delete expired scoped entries and keep their files.
- With it on, collect distinct IDs from `image_file` attributes before `deleteEntry()`. Delete files only after the expired entry is removed and after rechecking the direct parent folder.
- Preserve files in other folders, in child folders, without any Express entry, linked to a current entry, or linked to an unrelated Express entity. Reference checks include **all remaining Express entities**, not only entities covered by form retention.
- A file shared only by expired entries is deleted once, after the final reference is removed. Multiple fields referencing the same file do not inflate the count.
- A real Core image block produces usage records, and those records prevent deletion.
- Delete an eligible image with two real file versions and generated thumbnails. Verify the file/version database records and all captured physical originals and thumbnails are gone. The bulk fixture skips thumbnail generation; the separate version/thumbnail fixture explicitly creates and verifies them.
- Preview counts distinct eligible files and warns about direct files with no Express reference. It uses saved settings and assumes all currently expired entries are processed; time limits, deletion events, errors and concurrent changes may lower the next run's total.
- Removing the authorized folder produces a warning and skips file deletion while continuing expired-entry cleanup.
- An injected file-deletion exception and a Core event veto keep the affected files, record package-channel warnings and allow the next file/entry to be processed. Core's early-return veto leaves a transaction open; the package closes that extra transaction before logging so the warning is retained.
- If the deadline is reached during a file deletion, complete the entry's other file before stopping. The next entry remains for the next run.
- Run a real native Dashboard task via `ProcessTaskRunner`, Dashboard context, async transport and Symfony Worker with **205 entries and 205 real files**. Verify the persisted process is completed with exit code 0, the task completion timestamp is populated, native output contains `205 Express entries (205 uploaded files)`, and the task/process remain attached to Doctrine.
- No `$em->clear()` is used. Only package-loaded expired entry objects are explicitly detached where still managed; Core file objects remain managed because loaded attribute values may still reference them.

## Core findings: upload folder and private storage

The relevant APIs are available on both tested Core versions:

- File attribute type: **`image_file`**, stored by `Concrete\Core\Entity\Attribute\Value\Value\ImageFileValue`. The value provides the linked file ID. Remaining Express references are checked against `ExpressEntityEntryAttributeValues`, `ExpressEntityEntries` and `atFile` using read-only queries.
- The native folder widget is `Concrete\Core\Form\Service\Widget\FileFolderSelector::selectFileFolder()`. Root and invalid/deleted folders are rejected on the server as well as blocked in the normal UI.
- In the Express Form block, the exact upload target is **Options → Files → Add uploaded files to folder** (`concrete/blocks/express_form/form.php`). The block moves uploaded file nodes to the selected folder after saving the entry (`controller.php`). No additional block setting is invented by the package.
- **Storage location can be assigned per folder**, using File Manager → **Edit Folder → Storage Location**. See Core's `controllers/dialog/tree/node/file_folder/edit.php`, its dialog view, and `Tree\Node\Type\FileFolder::setTreeNodeStorageLocation()`.
- `Tree\Node\Type\File::move()` applies the target folder's storage location to the moved file. Runtime tests verified that a newly moved upload is physically stored in a private local directory outside the web root, without a public relative URL. They also verified that changing a folder's setting **does not migrate existing files**.
- Set up the location under **Dashboard → System & Settings → Files → File Storage Locations**, assign it to the dedicated folder, migrate older files separately and restrict download permissions. Core can still serve downloads according to its permissions; a non-public storage location removes the direct public-storage URL.
- File removal uses **`Concrete\Core\Entity\File\File::delete()`**, which invokes version deletion with physical-file/thumbnail cleanup. The package issues no direct file-deletion SQL and does not manually unlink managed uploads.

## Core findings: usage checks and limits

Both versions provide **`FileUsageRecord` / `FileUsageRepository`** and **`Concrete\Core\File\Tracker\UsageTracker`**. Recorded page/block uses prevent deletion. The actual image-block test uses Core's tracker; the package does not manufacture a usage row for that assertion.

This tracker is **not a complete proof of non-use**. It collects participating controllers/attributes implementing `FileTrackableInterface`; hard-coded file URLs, external references and custom integrations that do not participate may be absent. Therefore the package uses positive usage records as an additional conservative guard, while the dedicated, explicitly authorized folder remains essential. No universal “unused everywhere” guarantee is made. This limitation is explained in the settings, both READMEs and Marketplace description. The preview uses the same recorded-use guard. Stale records conservatively keep a file.

A failed/vetoed deletion can leave an unlinked file after its expired entry has gone. It is logged and **not** swept or automatically retried later, consistent with the requirement that unlinked files must never be deleted. Folder removal has the same conservative result for associated uploads.

## Browser and packaging checks

The Dashboard renders the new section and native folder selector on both 9.3.9 and 9.5.7. The 9.5.7 browser pass additionally verified disabling the toggle for an inactive Express period and root selection, activating it after a valid folder selection, and submitting without the separate file confirmation: the server rejected that request and retained the disabled saved settings. No production settings were changed.

All **11 PHP files** (including test scripts) linted under PHP 8.2.35 and 8.4.27. Composer JSON, local Markdown links, `git diff --check`, German gettext parsing, source-message coverage and translation placeholders passed. The external PO gained 23 new translations. No `languages/` directory or compiled catalog is included.

The tested payload came from `git archive` of implementation commit `c320ebb13d2e987f1c5d0ca4955948a60a05c973`. Subsequent changes only add verification evidence and correct the lifecycle assertion for Core's persisted boolean representation; the archive payload remains identical. [package-sha256.json](test-results/package-sha256.json) records hashes of all **15** distributable files. `.gitattributes` excludes tests, verification material, translation source, listing-only material and local development tools. The final ZIP is generated from the delivered feature-branch HEAD, and every file is compared against the tested payload. Its `.sha256` companion contains the archive checksum.

## Open release tasks

- Publisher review, merge into `main` and tagging **1.1.0** remain manual, as requested, after 1.0.0 Marketplace approval. No branch tag is created because Packagist may publish tagged versions.
- Publish/update translations through translate.concretecms.org before claiming the new German strings are available from Dashboard language updates. The delivery only updates the external PO source.
- Check actual deployment folders and custom file consumers against the documented usage-tracker limitation before enabling upload deletion.

There are no failing checks or unresolved implementation blockers in the tested scope.
