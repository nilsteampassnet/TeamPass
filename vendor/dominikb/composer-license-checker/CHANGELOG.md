# Changelog

All notable changes to `composer-license-checker` will be documented in this file

## 2.6.0 2024-07-06

### Added

- Add documentation on exit codes to expect from any `composer-license-checker` command. ([#41](https://github.com/dominikb/composer-license-checker/pull/41))
- Verify the exist code of the internal call to `composer licenses` to determine if the command was successful. ([#41](https://github.com/dominikb/composer-license-checker/pull/41))

### Changed

- Improve type documentation and clean up `composer.json` configuration. ([#40](https://github.com/dominikb/composer-license-checker/pull/40)) 

Thanks to: [SvenRtbg](https://github.com/SvenRtbg)

## 2.5.1 - 2024-01-05

### Changed

- Use JSON format when generating output with `composer licenses` instead of parsing text output. ([#33](https://github.com/dominikb/composer-license-checker/pull/37))

## 2.5.0 - 2023-11-16

### Added

- Add support for Symfony 7. ([#32](https://github.com/dominikb/composer-license-checker/pull/32))

Thanks to: [keulinho](https://github.com/keulinho)

## 2.4.3 - 2023-10-04

### Change

- Update required composer version to mitigate CVE exposure. ([#29](https://github.com/dominikb/composer-license-checker/pull/29))

Thanks to:  [phansys](https://github.com/phansys)

## 2.4.2 - 2023-08-13

### Change

- Update integration with [tldrlegal](https://www.tldrlegal.com/) to work with their new website.

## 2.4.1 - 2023-05-19

### Change

- Use `--dev` flag in documentation for installing the package.
- Fix deprecation warning ([#24](https://github.com/dominikb/composer-license-checker/pull/24))

Thanks to: [manavo](https://github.com/manavo)

## 2.4.0 - 2022-04-30

### Added
- __--show-packages__ and __--grouped__ options for the report command ([#22](https://github.com/dominikb/composer-license-checker/pull/22))
- __--filter__ to restrict queried and displayed licenses ([#22](https://github.com/dominikb/composer-license-checker/pull/22))

Thanks to: [dkemper](https://github.com/dkemper)

## 2.3.0 - 2022-03-02

### Change
- Composer: allow for `psr/simple-cache` versions 2 and 3
- Allow for `v6` of all symfony dependencies

## 2.2.0 - 2021-06-02

### Added
- __--allow__ option to always allow a specific package or author/vendor

## 2.1.0 - 2020-12-31

### Added
- Support for PHP 8

### Changed
- Upgrade PHPUnit major version from `7` to `9`
- Upgraded several dependencies to work with newer PHP versions. Major version bump includes [symfony/dom-crawler](https://github.com/symfony/dom-crawler).

### Removed
- Dropped support for PHP 7.1 and 7.2 [(see: supported versions)](https://www.php.net/supported-versions.php)

## 2.0.0 - 2020-06-17

### Changed
- Terminology for black/whitelist changed to block/allowlist (This changes the CLI interface of the `CheckCommand` )
- `Command` classes now return an integer exist code

## 1.0.1 - 2019-04-06

### Added
- Added Scrutinizer code coverage

### Changed
- Fixed path to autoloader when installed with composer

### Removed

## 1.0.0 - 2019-04-06

Initial Release

### Added
- `check` and `report` commands
- summary lookup on tldrlegal
- caching of lookups

### Changed

### Removed
