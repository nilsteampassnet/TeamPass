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

``` bash
./vendor/bin/composer-license-checker check \
        --allowlist MIT \ # Fail if anything but MIT license is used
        --blocklist GPL \ # Fail if any dependency uses GPL
        --allow dominikb/composer-license-checker # Always allow this dependency regardless of its license

vendor/bin/composer-license-checker report -p /path/to/your/project -c /path/to/composer.phar
```

### Exit codes

Any command returns with one of these exit codes:

- 0: Ok
- 1: Offending licenses found in check, or a problem occurred when creating a report
- 2: Internal error when executing the command, may indicate problems calling Composer internally

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
