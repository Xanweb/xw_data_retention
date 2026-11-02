# Combined release verification — 0.9.3

Date: **2026-11-02**. Version **0.9.3** combines both feature branches on `main`:

- `feature/delete-form-uploads` at `6951476414412687d1a10b162fa9458c528a0adf`.
- `feature/page-versions` at `89e420b630122162ddc7cc67637cd75fb935222b`.

Both branch heads are retained as merge ancestors. The release is based on main
`15f3486b963a8088f5a68a124cd307b9d23d2e83`. The upload branch's experimental
1.1.0 numbering is superseded by the owner's explicit combined version 0.9.3;
no released 1.1.0 version is implied. Existing feature branches and release tags
are preserved. No Marketplace submission or production deployment is included.

## Result

All **528 feature/integration assertions** passed, plus lifecycle audits in
every matrix cell. Acceptance is **keine Meldungen aus dem Paket**. E_ALL was
enabled from PHP startup, including upgrade and lifecycle processes. Core and
vendor diagnostics are retained separately, without changing Core or suppressing
reporting.

| Core | PHP | Upload checks | Page-version checks | Combined checks | Package diagnostics | Upstream suite / lifecycle diagnostics |
| --- | --- | --- | --- | --- | --- | --- |
| 9.3.9 | 8.2.35 | 45 | 76 | 11 | 0 | 10 / 0 |
| 9.3.9 | 8.4.27 | 45 | 76 | 11 | 0 | 297 / 9 |
| 9.5.7 | 8.2.35 | 45 | 76 | 11 | 0 | 10 / 0 |
| 9.5.7 | 8.4.27 | 45 | 76 | 11 | 0 | 21 / 0 |

Diagnostic numbers count repeated occurrences, not distinct issues. They include
Core's dynamic FileLogger property and PHP 8.4 Core/vendor nullable-signature and
other deprecations. See the [matrix](test-results/release-0.9.3/matrix.json),
[four logs](test-results/release-0.9.3/) and
[tested payload manifest](test-results/release-0.9.3/package-sha256.json).
Workstation paths are redacted in the logs.

Two new disposable Concrete installations were created specifically for the
combined release. Core 9.3.9 uses the
[official complete release](https://github.com/concretecms/concretecms/releases/tag/9.3.9),
including its vendor dependencies. Core 9.5.7 is the local read-only Core reporting
that version. Both use independent `xw_retention_release093_93` / `_95` databases
in a dedicated MySQL instance with no TCP listener. No customer data or server
configuration was changed. Compatibility with 9.3.0 remains the existing package
minimum; the combined features were tested on 9.3.9 and 9.5.7.

## Merge decisions

Both task drivers remain registered. The merged upgrade executes
`Policy::initializeUploads()` and installs the page-version task after Core's
upgrade/schema processing. Installation creates both tasks and the owned
run-counter table. Uninstall removes both tasks, their schedules, the owned table
and only the package's configuration namespace.

The original form/log settings and authorized upload folder live under
`retention.*`; page-version policy remains under `page_versions.*`. The form/log
schedule is 03:10, the independent page-version schedule 03:40. The Dashboard
retains the Core file-manager asset, upload selector, its separate irreversible
file-deletion confirmation, and the page-version form/save action with its own
CSRF token and confirmation. Both translation sets are merged in the external
German catalog. README files, changelog and listing reflect combined version
0.9.3.

Historical feature evidence is preserved. Upload evidence is under
`test-results/uploads-history/`; the original page-version evidence remains under
`test-results/`. Both old reports are marked historical and link here.

## Lifecycle and upgrade

Each cell restores an original-main **0.9.0** baseline, saves **2/3/6-month**
Express/legacy/log settings and verifies that neither new feature existed.
It then replaces the package with the exact 0.9.3 archive and performs a real
`c5:package:update xw_data_retention`, without force or artificial version staging.

The audit verifies version 0.9.3, both distinct task handles, the run-counter
table, retention-period preservation, initialized upload defaults, page-version
defaults and absence of an unwanted new schedule. **Both new deletion features
remain disabled after upgrading.** The original active form/log schedule remains
present without duplication.

After the feature suites, uninstall is checked for both task removals, absence
of owned Config rows and run table, unchanged unrelated configuration and
preservation of all versions outside the package-owned Dashboard page. Core
normally removes that owned page and its history. Fresh reinstall is then audited:
all form/log periods and both new deletion features are off, with no schedules.

## Cross-feature and regression coverage

The 11 combined assertions enable both policies, verify their separate settings
and schedules, then disable each in turn while checking that the other remains
active. They load the actual Dashboard controller and render the complete
Dashboard through Core's normal lifecycle with the disposable administrator.
Both forms, folder selector, version fields and required confirmations render.
The same checks verify both features can subsequently be disabled independently.

The 45 upload checks include authorization, direct-folder scoping, reference
protection, real Core usage tracking, versions and physical thumbnails, private
storage, missing folders, errors/vetoes, deadlines, and a real native queued
process deleting 205 entries/files across the batch boundary.

The 76 page-version checks include count and creation-age intersection, approval,
future schedules, real open workflows and mid-run workflow protection, optional
stacks/global areas, exclusions, languages, validation, Core-task warning,
per-version errors, native batch totals, shared-block retention and a bounded
225-version fixture. Full frontend pages, editor version lists and retained block
content are exercised after cleanup. Memory growth stays below the suite's
32 MiB bound for that fixture.

The upload worker test now waits for its own native process to complete, matching
the existing page-version worker test, so unrelated Core queue messages are also
processed without prematurely stopping the test. No entire EntityManager clear
is introduced into either cleanup implementation.

## Distribution

The installable ZIP is `dist/xw_data_retention-0.9.3.zip`, built using **git archive**
with the single top-level directory `xw_data_retention/`. It contains **26 files**.
Tests, reports, listing material, development utilities and external translations
are excluded. There is no bundled `languages/` directory. A companion `.sha256`
records the final archive checksum.

The matrix installed and ran from the extracted archive created at `51a84e7`.
Later changes are test-harness refinements and verification documents only.
The final archive is rebuilt from the delivered branch HEAD, and all 26 entries
and source files are compared against the tested SHA-256 manifest. Thus the
final commit's ZIP contains the exact tested package payload.

All **23 PHP files**, including tests, lint under PHP 8.2.35 and 8.4.27. The merged
German PO parses through the Core gettext library, includes both feature sets
and passes format-placeholder compilation checks. Its metadata is 0.9.3 and it
remains outside the distributable package.

## Remaining release considerations

- Open workflows intentionally protect the entire page, retaining more history
  until the workflow completes; no serialized workflow data is parsed in SQL.
- Core's Remove Old Page Versions schedule should be disabled when using the
  configurable page-version feature to avoid conflicting retention rules.
- Express file protection relies on recorded references; hard-coded URLs and
  custom integrations may not participate. Use a dedicated authorized folder.
- German source strings still need publication via translate.concretecms.org for
  Dashboard language updates.
- Histories larger than the 225-version fixture have not been benchmarked;
  runtime reads remain bounded. The feature suites do not establish compatibility
  with every third-party theme, block or workflow integration.

No failing checks or unresolved merge blockers remain in the tested scope.
