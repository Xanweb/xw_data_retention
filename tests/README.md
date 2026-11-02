# Combined release tests — 0.9.3

Use only disposable Concrete sites named **Xanweb Retention Release Test** with
MySQL databases **xw_retention_release093_93** or **xw_retention_release093_95**.
The scripts enforce both guards. They create and permanently delete real files,
Express entries, version histories, stacks, private storage and logs. Never run
against customer databases. The feature suites also retain their original
separate upload/page-version test-site guards for development.

Test the exact `git archive` distribution on Concrete 9.3.9 and 9.5.7 with PHP
8.2 and 8.4. Enable E_ALL at process startup. Acceptance is **no diagnostics from
the package**; preserve upstream Core/vendor diagnostics separately.

For each matrix cell:

1. Install original main 0.9.0; run `lifecycle.php seed` to save 2/3/6-month periods.
2. Replace it with the 0.9.3 archive and run `c5:package:update xw_data_retention`.
3. Run `lifecycle.php upgraded`; it checks both new hooks and disables the old
   periods only after verifying that upgrade preserved them.
4. Run `combined.php`, `uploads.php`, `lifecycle.php cleanup`, then
   `page_versions.php`. The combined test checks both policy namespaces,
   independent schedules, Dashboard datasets, real forms and confirmations.
5. Run `lifecycle.php prepare-uninstall`, uninstall the package, then run
   `lifecycle.php uninstalled`. This verifies owned tasks, config and table
   removal while preserving unrelated config and page histories.
6. Reinstall 0.9.3 and run `lifecycle.php defaults`.

Invoke test scripts from each test site's root using:

```sh
/path/to/php concrete/bin/concrete c5:exec /absolute/path/to/repository/tests/combined.php
/path/to/php concrete/bin/concrete c5:exec /absolute/path/to/repository/tests/uploads.php
/path/to/php concrete/bin/concrete c5:exec /absolute/path/to/repository/tests/page_versions.php
```

`uploads.php` covers authorization, direct-folder scoping, current/shared/other
Express references, Core usage tracking, image versions and physical thumbnails,
private storage, missing folders, event vetoes/errors, transaction cleanup,
deadlines and a real queued Dashboard process with 205 files. Cleanup of its
empty private storage locations runs in a fresh process.

`page_versions.php` covers count/age rules, approval and schedules, actual open
workflows, stacks/global areas, excluded pages, languages, invalid settings,
Core-task warnings, mid-run workflow changes, bounded reads/memory, shared
blocks, per-version failure continuation and real queued Dashboard tasks. It
renders affected pages and retained blocks through the Core frontend/editor APIs.

Development tests, reports and translation source are excluded from distribution
using `.gitattributes`. Historical branch verification remains in `docs/`;
`docs/verification-0.9.3.md` describes the combined release.
