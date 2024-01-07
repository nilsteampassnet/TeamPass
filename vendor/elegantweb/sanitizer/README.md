# sanitizer

![GitHub release (latest by date)](https://img.shields.io/github/v/release/elegantweb/sanitizer?style=flat-square)
![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/elegantweb/sanitizer/test.yml?style=flat-square)

> Sanitization library for PHP and the Laravel framework.

## Installation

``` bash
composer require elegantweb/sanitizer
```

## Usage

``` php
use Elegant\Sanitizer\Sanitizer;
use Elegant\Sanitizer\Filters\Enum;

$data = [
    'title' => ' ',
    'name' => ' sina ',
    'birth_date' => '06/25/1980',
    'email' => 'JOHn@DoE.com',
    'json' => '{"name":"value"}',
    'enum' => 'H',
];

$filters = [
    'title' => 'trim|empty_string_to_null',
    'name' => 'trim|empty_string_to_null|capitalize',
    'birth_date' => 'trim|empty_string_to_null|format_date:"m/d/Y","F j, Y"',
    'email' => ['trim', 'empty_string_to_null', 'lowercase'],
    'json' => 'cast:array',
    'enum' => ['trim', new Enum(BackedEnum::class)],
];

$sanitizer = new Sanitizer($data, $filters);

var_dump($sanitizer->sanitize());
```

Will result in:

``` php
[
    'title' => null,
    'name' => 'Sina',
    'birth_date' => 'June 25, 1980',
    'email' => 'john@doe.com',
    'json' => ['name' => 'value'],
    'enum' => BackedEnum::Hearts,
];
```

### Laravel

In Laravel, you can use the Sanitizer through the Facade:

``` php
$newData = \Sanitizer::make($data, $filters)->sanitize();
```

You may also Sanitize input in your own FormRequests by using the SanitizesInput trait, and adding a `filters` method that returns the filters that you want applied to the input.

``` php
namespace App\Http\Requests;

use Elegant\Sanitizer\Laravel\SanitizesInput;

class MyAwesomeRequest extends Request
{
    use SanitizesInput;
    
    public function filters()
    {
        return [
            'name' => 'trim|capitalize',
        ];
    }
}
```

#### Optional

If you are planning to use sanitizer for all of your HTTP requests, you can optionally disable
Laravel's `TrimStrings` and `ConvertEmptyStringsToNull` middleware from your HTTP kernel.

```php
protected $middleware = [
    [...]
    // \App\Http\Middleware\TrimStrings::class,
    // \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    [...]
];
```

Then, instead, you can use `trim` and `empty_string_to_null` filters:

```php
$filters = [
    'some_string_parameter' => 'trim|empty_string_to_null',
];
```

## Available Filters

The following filters are available out of the box:

 Filter                   | Description
:-------------------------|:-------------------------
 **trim**                 | Trims the given string
 **empty_string_to_null** | If the given string is empty set it to `null`
 **escape**               | Removes HTML tags and encodes special characters of the given string
 **lowercase**            | Converts the given string to all lowercase
 **uppercase**            | Converts the given string to all uppercase
 **capitalize**           | Capitalizes the given string
 **cast**                 | Casts the given value into the given type. Options are: integer, float, string, boolean, object, array and Laravel Collection.
 **format_date**          | Always takes two arguments, the given date's format and the target format, following DateTime notation.
 **strip_tags**           | Strips HTML and PHP tags from the given string
 **digit**                | Removes all characters except digits from the given string
 **enum**                 | Casts the given value to its corresponding enum type

## Custom Filters

It is possible to use a closure or name of a class that implements `Elegant\Sanitizer\Contracts\Filter` interface.

``` php
class RemoveStringsFilter implements \Elegant\Sanitizer\Contracts\Filter
{
    public function apply($value, array $options = [])
    {
        return str_replace($options, '', $value);
    }
}

$filters = [
    'remove_strings' => RemoveStringsFilter::class,
    'password' => fn ($value, array $options = []) => sha1($value),
];

$sanitize = new Sanitizer($data, $filters);
```

### Laravel

You can easily extend the Sanitizer library by adding your own custom filters, just like you would the Validator library in Laravel, by calling extend from a ServiceProvider like so:

``` php
\Sanitizer::extend($filterName, $closureOrClassName);
```

## Inspiration

- [WAAVI Sanitizer](https://github.com/Waavi/Sanitizer)
