# Contributing

First of all, **thank you** for contributing.

Bugs or feature requests can be posted online on the GitHub issues section of the project.

Few rules to ease code reviews and merges:

- You MUST follow the [PSR-12](http://www.php-fig.org/psr/psr-12/) coding standards.
- You MUST run the test suite.
- You MUST write (or update) unit tests when bugs are fixed or features are added.
- You SHOULD write documentation.

We use [Git-Flow](http://jeffkreeftmeijer.com/2010/why-arent-you-using-git-flow/) to automate our git branching
workflow.

To contribute use [Pull Requests](https://help.github.com/articles/using-pull-requests), please, write commit messages
that make sense, and rebase your branch before submitting your PR.

May be asked to squash your commits too. This is used to "clean" your Pull Request before merging it, avoiding commits
such as fix tests, fix 2, fix 3, etc.

Run test suite
------------

* install Composer by following the [official installation instructions](https://getcomposer.org/download/), which
  verify the installer signature before running it. Never pipe the installer straight into `php`.
* install dependencies: `composer install`
* run tests: `vendor/bin/phpunit --configuration .ci-tools/phpunit.xml.dist`

Run the full CI suite locally
------------

The quality checks that gate a Pull Request all run through [Castor](https://castor.jolicode.com/), inside the same
`ghcr.io/spomky-labs/phpqa` image the CI uses, so the tool versions match:

```sh
castor ecs        # coding standards (castor ecs_fix to apply)
castor rector     # automated refactoring, dry-run (castor rector_fix to apply)
castor phpstan    # static analysis
castor deptrac    # architecture layers
castor lint       # syntax check
castor phpunit    # tests, with coverage
```
