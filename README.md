#  Actions Pattern

[![Latest Version on Packagist](https://img.shields.io/packagist/v/returnearly/actions-pattern.svg?style=flat-square)](https://packagist.org/packages/returnearly/actions-pattern)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/returnearly/actions-pattern/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/returnearly/actions-pattern/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/returnearly/actions-pattern/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/returnearly/actions-pattern/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/returnearly/actions-pattern.svg?style=flat-square)](https://packagist.org/packages/returnearly/actions-pattern)

A minimal package for using the actions pattern in Laravel.

## Installation

You can install the package via composer:

```bash
composer require returnearly/actions-pattern
```

## Action Class
    
```php
<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class MyCustomAction
{
    public function __construct(
        private MyDependency $dependency
    ) {
    }

    public function handle(int $amount): void
    {
        $amount += 10;
    
        $this->dependency->doSomething($amount);
    }
}

```

## Usage

```php
\App\Actions\MyCustomAction::make()->handle($item)
```

or via dependency injection

```php
use App\Actions\MyCustomAction;

class MyController
{
    public function __construct(
        private MyCustomAction $action
    ){
    }

    public function __invoke($item)
    {
        $this->action->handle($item);
    }
}

```

## Create An Action
    
```bash

php artisan make:action MyCustomAction --test

```
Running the above command will create a new action class in the `app/Actions` directory and the corresponding test in the `tests/Feature/Actions` directory.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Tom Schlick](https://github.com/tomschlick)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
