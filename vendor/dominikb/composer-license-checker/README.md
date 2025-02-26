# Composer License Checker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dominikb/composer-license-checker.svg?style=flat-square)](https://packagist.org/packages/dominikb/composer-license-checker)
[![Build Status](https://img.shields.io/github/workflow/status/dominikb/composer-license-checker/run-tests?style=flat-square)]()
[![Quality Score](https://img.shields.io/scrutinizer/g/dominikb/composer-license-checker.svg?style=flat-square)](https://scrutinizer-ci.com/g/dominikb/composer-license-checker)
[![Scrutinizer coverage](https://img.shields.io/scrutinizer/coverage/g/dominikb/composer-license-checker.svg?style=flat-square)](https://scrutinizer-ci.com/g/dominikb/composer-license-checker)
[![Total Downloads](https://img.shields.io/packagist/dt/dominikb/composer-license-checker.svg?style=flat-square)](https://packagist.org/packages/dominikb/composer-license-checker)

Quickly scan your dependencies, see what licenses they use or check in your CI that no unwanted licenses were merged.

The lookup of the summaries for every license done on [https://tldrlegal.com/](https://tldrlegal.com/).  
Please inform yourself in more detail about the licenses you use and do not use the provided summary as your sole information.

## Installation

You can install the package via composer:

```bash
composer require --dev dominikb/composer-license-checker
```

## Usage

Two separate commands are provided:
* `./composer-license-checker check`
* `./composer-license-checker report`

Use `./composer-license-checker help` to get info about general usage or use the syntax `./composer-license-checker help COMMAND_NAME` to see more information about a specific command available. 

```bash
./vendor/bin/composer-license-checker check \
        --allowlist MIT \ # Fail if anything but MIT license is used
        --blocklist GPL \ # Fail if any dependency uses GPL
        --allow dominikb/composer-license-checker # Always allow this dependency regardless of its license

vendor/bin/composer-license-checker report -p /path/to/your/project -c /path/to/composer.phar
```

### Path to composer

By default, this tool assumes that "composer" is in your path and a valid command that will call Composer.

If that isn't the case, add the `-c` or `--composer` option with the path where to find Composer instead.
This tool comes with Composer installed as a dependency, so you may start with `--composer ./vendor/bin/composer`, given that you are in this tool's root directory when executing a license check.

If this tool cannot find Composer, it will exit with status code 2, see below.

### Exit codes

Any command returns with one of these exit codes:

- 0: Ok
- 1: Offending licenses found in check, or a problem occurred when creating a report
- 2: Internal error when executing the command, may indicate problems calling Composer internally

### Dependencies without a license

Some dependencies might not have a license specified in their `composer.json`.
Those will be grouped under the license `none`.

```bash
# Reporting a dependency without a license will look like this
./composer-license-checker report --show-packages

#  Count 1 - none (-)
#  +-----+---------+------+
#  | CAN | CAN NOT | MUST |
#  +-----+---------+------+
#
#  packages: somepackage/without-a-license
```

You can add the imagined license `none` to your allowlist or blocklist to handle those dependencies.

```bash
# Allow dependencies without a license
./composer-license-checker check --allowlist none

# Disallow dependencies without a license
./composer-license-checker check --allowlist GPL --blocklist none
```

### Testing

``` bash
composer test
```

Code coverage reports are output to the `build` folder. See `.phpunit.xml.dist` for more testing configuration.

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email bauernfeind.dominik@gmail.com instead of using the issue tracker.

## Credits

- [Dominik Bauernfeind](https://github.com/dominikb)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
