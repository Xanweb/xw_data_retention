# Integration tests for 1.1.0

Run only on disposable Concrete installations named **Xanweb Retention Upload Test**, with database names `xw_retention_110_93` or `xw_retention_110_95`. These guards are deliberate: the tests create and permanently delete real Express entries, files, versions, thumbnails and logs. Never point them at a customer database.

The new upload feature is tested on Concrete 9.3.9 and 9.5.7, with PHP 8.2 and 8.4. Tests use Core APIs and libraries; no separate test dependency is required. Supply the test database connection and appropriate PHP binary for each installation.

From the test site's root, with the repository outside the site's public directory:

```sh
php concrete/bin/concrete c5:package:install --languages=no xw_data_retention
php concrete/bin/concrete c5:exec /path/to/repository/tests/lifecycle.php defaults
php concrete/bin/concrete c5:exec /path/to/repository/tests/uploads.php
php concrete/bin/concrete c5:exec /path/to/repository/tests/lifecycle.php cleanup
```

For upgrade verification, install a copy of the previous main code with only its `pkgVersion` set to `1.0.0`, run `lifecycle.php seed-100`, replace the package with the 1.1.0 archive, update it through `c5:package:update`, then run `lifecycle.php upgraded`. This explicitly tests the version transition; it does not claim that the repository already contains a released 1.0.0 tag.

`uploads.php` checks validation before config writes, disabled behavior, direct-folder scoping, shared/current/unrelated Express references, deduplication, real Core image-block usage, actual versions and physical thumbnails, private storage per folder, missing folders, error and event-veto continuation, transaction cleanup, deadlines, and a native queued Dashboard process with 205 real files. Only the bulk fixture skips thumbnail generation; the version/thumbnail fixture generates and verifies physical thumbnails. The worker uses Core's Dashboard context, real async transport, event dispatcher and Symfony Worker. It verifies the persisted completed process, exit code, task completion timestamp, process log and still-managed runner objects. Empty fixture storage locations are removed by `lifecycle.php cleanup` in a fresh Doctrine context so stale Core File objects cannot refer to a removed storage entity.

Enable `E_ALL` from PHP startup. Acceptance: **no diagnostics originating from the package**. Keep upstream Core/vendor diagnostics visible and report them separately. Test scripts and translation source are excluded from the archive through `.gitattributes`.
