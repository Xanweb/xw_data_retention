# Verification — Page version retention (Unreleased)

Date: **2026-10-27**. Branch: `feature/page-versions`, based on `main` at `15f3486b963a8088f5a68a124cd307b9d23d2e83`. **Package version remains 0.9.0**, exactly as on that main. No merge, tag, Packagist release or Marketplace submission is included. The sibling upload-retention branch is not part of this build.

## Result

All **304 feature assertions** passed across four Core/PHP combinations, plus lifecycle checks. Acceptance: **keine Meldungen aus dem Paket**. All tests ran on disposable, independently copied test sites and separate private MySQL databases with no TCP listener. No customer data was involved.

| Concrete CMS | PHP | Feature checks | Package diagnostics | Upstream suite diagnostics | Lifecycle |
| --- | --- | --- | --- | --- | --- |
| 9.3.9 | 8.2.35 | 76 / 76 | 0 | 7 | pass |
| 9.3.9 | 8.4.27 | 76 / 76 | 0 | 145 | pass |
| 9.5.7 | 8.2.35 | 76 / 76 | 0 | 7 | pass |
| 9.5.7 | 8.4.27 | 76 / 76 | 0 | 11 | pass |

Diagnostic numbers count repeated occurrences, not unique issues. PHP 8.2 exposes Core's dynamic `FileLogger::$filePath` property when native batch/process logging is exercised. PHP 8.4 additionally reports upstream nullable-signature/E_STRICT and other Core/vendor deprecations. E_ALL was enabled from process startup; diagnostics were not suppressed and Core files were not changed. [matrix.json](test-results/matrix.json) and the four [logs](test-results/) contain the evidence, with local workstation paths redacted.

Core 9.3.x was tested using the official complete **9.3.9** distribution. Core **9.5.7** was the local read-only Core reporting that version. The new feature was not rerun on 9.3.0; the existing minimum package requirement is unchanged.

## Lifecycle

Each matrix cell installed the unchanged main baseline, seeded the original Express/legacy/log retention periods to **2/3/6 months**, replaced the package with the feature archive and ran `c5:package:update --force xw_data_retention`. The force flag is required for this evaluation branch because the version is intentionally unchanged. The upgrade added the new task and owned table, retained all original periods and left page-version cleanup disabled with keep=10, no age limit and stacks off.

After the feature suite, uninstall removed both package tasks, their schedules, the package configuration namespace and `XwDataRetentionPageVersionRuns`. Versions outside the package-owned dashboard page and unrelated configuration were unchanged. Core normally removes the add-on's own dashboard page and its version; this is excluded from the preservation comparison. Reinstall restored the disabled defaults and created no page-version schedule.

## Selection and protected versions

The independent configuration namespace is `page_versions.*`. The original `retention.*` policy and its 03:10 schedule remain unchanged. Enabling the new feature requires the existing explicit deletion confirmation. The keep count is validated as an integer from **Policy::MIN_KEEP (10)** through **1000**. The minimum has one central constant and is used by validation, defaults, SQL selection and the UI. The optional age list is 1/2/3/6/12/24/36 months, or no limit; the cutoff uses the **version creation date**.

The newest N versions are ordered by **cvID descending**, matching Core's VersionList. A candidate is strictly below the Nth newest ID and, if configured, older than the creation-age cutoff. It must also be unapproved and have no future publication start. Keeping all approved versions can leave more than N versions; this includes approved scheduled/expired versions, not only the currently live one. The latest version is protected by the latest-N rule and is checked again through Core before deletion.

Trash paths, composer drafts, dashboard/system pages, aliases, external links and page templates are excluded. Trash/draft paths use Core configuration. The stack opt-in allows actual `Stacks` records through the system-page restriction while retaining the trash/draft/dashboard exclusions. Each page ID, language page, stack and global area is evaluated separately.

Preview and execution share the same read-only SQL selection. Preview returns **one SQL count query**, including distinct affected page IDs, without hydrating version objects. No window-function dependency is introduced. Preview uses saved settings; workflow/settings/version changes before execution can reduce the actual count.

## Workflow detection

Both Core versions expose open page workflows through **PageWorkflowProgress joined to WorkflowProgress**, with **wpIsCompleted = 0**. Core's `PageProgress::getList()` uses that same completion flag. The exact requested version lives in the serialized `WorkflowRequestObjects.wrObject`, exposed through `PageProgress::getWorkflowRequestObject()` and `ApprovePageRequest::getRequestedVersionID()`; it is not a normalized SQL column.

The package therefore **skips the entire page while any workflow is incomplete**. It does not parse serialized requests with SQL or load every workflow/version object just to produce the preview. Missing progress details also cause a conservative skip. This includes pending moves/deletes and requests without an explicit version ID, as well as approvals. It can retain additional history until the workflow is completed; this is an intentional conservative difference from protecting only one requested version.

Tests create a real serialized approval request and open PageProgress through Core APIs without sending workflow notifications. They verify preservation while open and eligibility after `markCompleted()`. Another test opens a workflow after the first deletion in an ongoing cleanup; every remaining version is immediately retained because all guards are rechecked before each destructive API call.

Relevant Core sources on both targets: `src/Workflow/Progress/PageProgress.php`, `src/Workflow/Progress/Progress.php`, `src/Workflow/Request/PageRequest.php` and `src/Workflow/Request/ApprovePageRequest.php`.

## Core protection and task differences

Core's `remove_old_page_versions` task retains the newest ten, all approved versions and the most recent version. It does **not** use this feature's configured count, age threshold, future-unapproved-date guard or open-workflow exclusion. Its 9.3.9 handler materializes the version list; the 9.5.7 handler processes groups of 100. Both use `BatchProcessTaskRunner` with one command per page.

Core's editor additionally refuses deletion of the last version or the currently active version. The package's minimum count and approved-version protection cover those conditions. **Version::delete() itself does not apply these selection/authorization guards**; the package must check them before calling it. No further automatic version-protection rule was found in that deletion API on either target. The package additionally excludes aliases/external links and page templates to avoid processing shared targets or master histories.

The dashboard warns whenever the Core task has a schedule and links to Tasks. Documentation recommends disabling that schedule when using this feature, because it can remove versions this add-on would retain. The warning and link were verified in the actual 9.5.7 Dashboard; SQL/API tests verify detection and removal of the warning condition on both versions.

## Execution and totals

The separate native task is **xw_delete_old_page_versions**, scheduled daily at **03:40** only while enabled. It is also manually runnable. The batch enumerates candidate page IDs and queues one command per page. A page fetches at most **100 version IDs** at once, advances by ID even on a failure, and reloads the current settings/guards immediately before each deletion. Only **Core Version::delete()** deletes versions; there is no package SQL deleting CollectionVersions or shared blocks.

A scoped request-cache pause prevents Core's legacy page/version/block request cache from growing with every deleted version. The prior cache state is restored for direct calls; Core's worker remains cache-disabled when it was already disabled. Page-specific cached entries are invalidated afterward. No EntityManager clear is used, and the native process/task remain managed by Doctrine. The 225-version test crosses three ID chunks and verifies less than 32 MiB growth during cleanup; it is not a million-version benchmark.

Version failures are logged on **xw_data_retention** and processing continues. If an event throws after Core already deleted a version, the count reflects the actual removed row. Tests inject such an event failure and verify continuation and logging.

Atomic run counters in the owned table aggregate deleted versions and processed pages across batch workers. The process-close listener writes the final totals to Core's native process log when process logging is enabled; individual page counts are also native task output. Completed counters older than 30 days are pruned. No submission content or page bodies are stored in the counter table. Tests verify the completed Dashboard process, exit code 0, completion timestamp, exact per-page command count, aggregate totals and final log message. A manual run while disabled also completes with zero deletions.

## Covered scenarios and rendering

The suite covers all requested count, age, approval, schedule, workflow, stack/global-area, trash/draft/system-page, language-page, validation, conflicting-Core-schedule and uninstall cases. It also verifies configuration/schedule independence, maximum keep count, a one-query preview, mid-run workflow changes, actual shared-block preservation, error continuation, multi-chunk processing, memory growth and a real async Dashboard batch/worker.

For **12 affected normal/language pages per matrix cell**, the full frontend page is rendered through its PageController view and actual theme after cleanup. Each editor VersionList loads all remaining versions, and blocks render from every retained version. A real browser pass on 9.5.7 confirmed published content and the Editor → Versions panel showing retained versions **25 through 16**, with version 25 marked Live.

The frontend test uses the renderer's returned HTML and a bound page theme, matching Core's rendering setup. The schema test uses `createSchemaManager()` where provided and the older `getSchemaManager()` interface on 9.3.9. These test-harness adaptations do not change the runtime package behavior.

## Packaging and integration notes

Implementation payload: `52b29faa2d6e06e24ea24b10b899726063b0ce68`, archived using **git archive**. Subsequent changes add test refinements and verification evidence only. The final archive is rebuilt from the delivered branch HEAD and each of its **25 files** is compared with [package-sha256.json](test-results/package-sha256.json), proving equality with the tested payload. A companion `.sha256` records the final ZIP checksum. Tests, reports, translations, listing-only material and development utilities are excluded. There is no languages directory in the package. All **20 PHP files**, including the tests, linted under PHP 8.2.35 and 8.4.27. The external PO gained **24** German strings; parsing, source coverage and placeholder checks passed.

To reduce conflicts with `feature/delete-form-uploads`, this feature lives in `src/PageVersions/`, an independent `page_versions.*` config subtree and its own element/form/save action. The original Policy, Cleanup and form-save logic are untouched. Future integration must retain both controller task registrations/install/upgrade hooks, both dashboard data sets/forms, both translation additions and both Unreleased notes. The sibling branch assigns 1.1.0; this branch deliberately retains main's 0.9.0. Release numbering remains the publisher's decision. No integration merge was performed.

## Open points

- Review the conservative **whole-page skip for an open workflow**. It is safe for approval versions but deliberately retains more history until that workflow closes.
- Select the eventual release version and merge the sibling features, then repeat the acceptance tests on that combined release. This branch is tested independently.
- Publish translations through translate.concretecms.org before expecting the new German strings in Dashboard language updates.
- Very large production histories were not benchmarked beyond the 225-version fixture; the runtime uses bounded 100-ID reads and avoids accumulating request-cache entries.

No failing checks or unresolved implementation blockers remain in the tested scope. No tag, main merge or Marketplace submission has been made.
