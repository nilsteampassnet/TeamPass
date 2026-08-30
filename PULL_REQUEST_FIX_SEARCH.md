## Description

This PR fixes inconsistent search results for items and folders located more than one level below a user's personal-folder root.

The command palette and the legacy quick search on the Items page applied an additional parent-based heuristic after resolving the user's accessible folders. That heuristic kept the personal root and its direct children, but incorrectly treated deeper descendants as foreign personal folders. As a result, an item could be visible while browsing its folder and discoverable from the full Search page, yet remain absent from both `Ctrl+K` and the Items-page search.

The change:

- makes the command palette use the existing `searchResolveFolderScope()` authorization primitive;
- removes the redundant depth-based personal-folder exclusion from `find.queries.php`;
- keeps limited search as an intersection with the authorized folder scope;
- continues to exclude folders belonging to other users' personal trees;
- applies the corrected scope to both item and folder results in the command palette;
- adds regression coverage for deeply nested personal folders and all three search entry points.

No cache rebuild, schema migration, or configuration change is required.

## Related issue

No linked issue. The problem was reproduced in a lab with a standard user's item stored in a deeply nested personal folder.

## Type of change

- [x] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (existing behaviour changes)
- [ ] Documentation
- [ ] Translation
- [ ] Refactor / maintenance

## How has this been tested?

Regression tests were added to verify that:

- a personal root, its child, and its grandchild remain searchable;
- a limited search can target a deeply nested personal folder;
- `find.queries.php`, `search.queries.php`, and `palette.queries.php` all use the shared folder-scope resolver;
- the former parent-depth heuristic cannot be reintroduced unnoticed.

Local validation performed:

- all four modified PHP files were parsed successfully for syntax errors;
- `git diff --check` completed successfully;
- the final diff was reviewed against `develop` and contains no unrelated changes.

The PHPUnit suite and PHPStan were not run locally because no PHP runtime is available in the local development environment. They are intentionally left unchecked below for CI verification.

Manual verification scenario:

- standard user with personal folders enabled;
- search for a deeply nested personal item through `Ctrl+K`;
- search for its containing folder through `Ctrl+K`;
- search for the item from the Items page with limited search both disabled and enabled;
- confirm that the full Search page continues to return the same item.

Admin, manager, and read-only roles were not manually exercised because the defect is specific to a standard user's own personal-folder hierarchy. The shared authorization primitive continues to subtract forbidden personal folders for every role.

## Checklist

- [ ] PHPStan level 4 passes (`php app/vendor/bin/phpstan analyse --memory-limit=2G`)
- [ ] The test suite passes (`php _tools/phpunit.phar` — see CONTRIBUTING.md for the one-time setup)
- [x] Every new public function has a docblock
- [x] Variable names and comments are in English
- [x] No `var_dump()` or `console.log()` left in the code
- [x] New `app/sources/*.queries.php` files have a matching `public/sources/` proxy shim
- [x] Changes to `teampassclasses` were applied to **both** copies (`app/includes/libraries/` and `app/vendor/`)
- [x] `app/vendor/composer/` is in its production form (`git checkout -- app/vendor/composer/`)

The proxy-shim and dual-copy checklist entries are not applicable because this PR adds no handler and changes no `teampassclasses` package.

## Impact on install / upgrade

- [x] No schema change
- [ ] Schema change — an `install/upgrade_run_X.X.X.php` script is included and the fresh install path was tested

## Screenshots

Not applicable. This PR changes search authorization and result scoping without changing the user interface.
