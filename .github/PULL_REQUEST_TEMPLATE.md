## Description

<!-- What does this PR change, and why? -->

## Related issue

<!-- e.g. Fixes #1234 -->

## Type of change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (existing behaviour changes)
- [ ] Documentation
- [ ] Translation
- [ ] Refactor / maintenance

## How has this been tested?

<!-- Describe what you ran. Mention the user roles you tested with (admin, manager,
     standard, read-only) and whether personal folders were on or off. -->

## Checklist

- [ ] PHPStan level 4 passes (`php app/vendor/bin/phpstan analyse --memory-limit=2G`)
- [ ] The test suite passes (`php app/vendor/phpunit/phpunit/phpunit`)
- [ ] Every new public function has a docblock
- [ ] Variable names and comments are in English
- [ ] No `var_dump()` or `console.log()` left in the code
- [ ] New `app/sources/*.queries.php` files have a matching `public/sources/` proxy shim
- [ ] Changes to `teampassclasses` were applied to **both** copies (`app/includes/libraries/` and `app/vendor/`)
- [ ] `app/vendor/composer/` is in its production form (`git checkout -- app/vendor/composer/`)

## Impact on install / upgrade

- [ ] No schema change
- [ ] Schema change — an `install/upgrade_run_X.X.X.php` script is included and the fresh install path was tested

## Screenshots

<!-- For UI changes. -->
