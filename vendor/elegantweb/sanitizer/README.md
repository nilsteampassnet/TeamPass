# sanitizer

![GitHub release (latest by date)](https://img.shields.io/github/v/release/elegantweb/sanitizer?style=flat-square)
![GitHub Workflow Status](https://img.shields.io/github/workflow/status/elegantweb/sanitizer/test?style=flat-square)

> Sanitization library for PHP and the Laravel framework.

## Installation

``` bash
composer require elegantweb/sanitizer
```

## Usage

``` php
use Elegant\Sanitizer\Sanitizer;

$data = [
    'name' => ' sina ',
    'birth_date' => '06/25/1980',
    'email' => 'JOHn@DoE.com',
    'json' => '{"name":"value"}',
];

$filters = [
    'name' => 'trim|capitalize',
    'birth_date' => 'trim|format_date:"m/d/Y","F j, Y"',
    'email' => ['trim', 'lowercase'],
    'json' => 'cast:array',
];

$sanitizer = new Sanitizer($data, $filters);

var_dump($sanitizer->sanitize());
```

Will result in:

``` php
[
    'name' => 'Sina',
    'birth_date' => 'June 25, 1980',
    'email' => 'john@doe.com',
    'json' => ['name' => 'value'],
];
```

### Laravel

In Laravel, you can use the Sanitizer through the Facade:

``` php
$newData = \Sanitizer::make($data, $filters)->sanitize();
```

You may also Sanitize input in your own FormRequests by using the SanitizesInput trait, and adding a filters method that returns the filters that you want applied to the input.

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
 **trim**                 | Trims a string
 **empty_string_to_null** | If the given string is empty set it to `null`
 **escape**               | Escapes HTML and special chars using php's filter_var
 **lowercase**            | Converts the given string to all lowercase
 **uppercase**            | Converts the given string to all uppercase
 **capitalize**           | Capitalize a string
 **cast**                 | Casts a variable into the given type. Options are: integer, float, string, boolean, object, array and Laravel Collection.
 **format_date**          | Always takes two arguments, the date's given format and the target format, following DateTime notation.
 **strip_tags**           | Strip HTML and PHP tags using php's strip_tags
 **digit**                | Get only digit characters from the string

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
