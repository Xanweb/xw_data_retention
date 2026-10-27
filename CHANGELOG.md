# Changelog

## Unreleased

- Add independent old-page-version retention with a configurable 10–1000 minimum, optional creation-age limit and SQL preview of versions and distinct pages.
- Preserve latest, approved and future-scheduled versions. Skip all versions on pages with open workflows, as well as trash, drafts, system pages, aliases and templates.
- Add optional stack/global-area cleanup using the same rules.
- Add a separately scheduled native batch task at 03:40, per-version error logging and aggregate run totals. Delete only through the Core version API.
- Warn when the conflicting Core Remove Old Page Versions task is scheduled.
- Keep the package version unchanged; release assignment remains pending.

## 0.9.0

- Initial Marketplace review build with independent Express Form, legacy Form and database-log retention settings and a native task.
