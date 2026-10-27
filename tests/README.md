# Page-version retention integration tests

Use only disposable installations named **Xanweb Page Version Retention Test**, with database names `xw_retention_pv_93` or `xw_retention_pv_95`. These tests create and permanently delete real versions and workflow records; never run them on a customer site.

Run via Core's `c5:exec` from a test site's root:

```sh
php concrete/bin/concrete c5:exec /path/to/repository/tests/lifecycle.php installed
php concrete/bin/concrete c5:exec /path/to/repository/tests/page_versions.php
php concrete/bin/concrete c5:exec /path/to/repository/tests/lifecycle.php prepare-uninstall
php concrete/bin/concrete c5:package:uninstall xw_data_retention
php concrete/bin/concrete c5:exec /path/to/repository/tests/lifecycle.php uninstalled
```

The suite covers disabled behavior, integer validation, independent schedules/configuration, single-query SQL preview, latest-N plus age protections, approved and scheduled versions, actual serialized Core workflow requests, a workflow opened during deletion, stacks/global areas, trash, composer drafts, dashboard/system pages, language-specific histories, error continuation, 225-version chunking and memory growth, Core schedule warnings, native async Dashboard batches with one command per page and aggregate totals, version-history loading and retained block rendering. The worker waits for its own process to complete; Core's incidental reindex jobs may be ahead of the batch in the queue.

For upgrade testing, install the original `main` archive, run `lifecycle.php seed`, replace it with the feature archive and run `c5:package:update --force xw_data_retention`, followed by `lifecycle.php upgraded`. `pkgVersion` deliberately remains 0.9.0 in this branch. The matrix is Concrete 9.3.9/9.5.7 with PHP 8.2/8.4, with E_ALL enabled from process startup. Acceptance: no diagnostics from the package; upstream diagnostics remain visible and are counted separately.

Fixtures are retained in the isolated database for frontend/editor browser checks. Their IDs are written to `page-version-fixtures.json` in the test site's root. Reset the disposable database between matrix cells. The archive excludes tests, reports and the external PO source through `.gitattributes`.
