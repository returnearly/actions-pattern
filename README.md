#  Actions Pattern

[![Latest Version on Packagist](https://img.shields.io/packagist/v/returnearly/actions-pattern.svg?style=flat-square)](https://packagist.org/packages/returnearly/actions-pattern)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/returnearly/actions-pattern/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/returnearly/actions-pattern/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/returnearly/actions-pattern/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/returnearly/actions-pattern/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/returnearly/actions-pattern.svg?style=flat-square)](https://packagist.org/packages/returnearly/actions-pattern)

A minimal package for using the actions pattern in Laravel.

## What is the actions pattern?

An **action** is a small, single-purpose class that encapsulates one unit of business logic — "process a refund", "register a user", "publish a post". Instead of scattering that logic across fat controllers, jobs, and listeners, you put it in one place and call it from anywhere.

Every action follows the same shape:

- It exposes **one** public entry point, `handle()` — the only method callers touch.
- Supporting logic lives in **private** methods, so the public surface stays small and the internals stay free to change.
- Its **dependencies arrive through the constructor** (repositories, services, even other actions). The action never reaches into the container itself, which keeps it trivial to test.
- Actions are **composed**, not wired into a pipeline: when one action needs another, it simply calls it. That is how you "chain" work together.

The `handle()` signature is entirely up to you — accept whatever arguments make sense, return whatever the caller needs; the package does not constrain it.

To turn a plain class into an action, implement `ActionsPatternInterface` and use the `ActionsPattern` trait. The trait adds a static `make()` factory that resolves the action from Laravel's container (so constructor dependencies are autowired).

## Requirements

- **PHP 8.3+**
- **Laravel 12+**

The test suite runs against PHP 8.3, 8.4 and 8.5 and Laravel 12 and 13.

## Installation

You can install the package via composer:

```bash
composer require returnearly/actions-pattern
```

## Action Class

An action is any class that implements `ActionsPatternInterface` and uses the `ActionsPattern` trait:

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class MyCustomAction implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private MyDependency $dependency,
    ) {
    }

    public function handle(int $amount): void
    {
        $this->dependency->doSomething($amount + 10);
    }
}
```

## Usage

Resolve and run an action in one call. `make()` pulls the instance from the container, so any constructor dependencies are autowired for you:

```php
\App\Actions\MyCustomAction::make()->handle($item);
```

Or inject it via the constructor and call `handle()` directly:

```php
use App\Actions\MyCustomAction;

final readonly class MyController
{
    public function __construct(
        private MyCustomAction $action,
    ) {
    }

    public function __invoke($item)
    {
        $this->action->handle($item);
    }
}
```

> Dependency injection is **constructor injection only**. Because `make()` resolves the action through the container, type-hinted constructor arguments are autowired. There is no method injection on `handle()` — pass its arguments explicitly.

## A Complete Example

Organize a feature into three clear layers:

1. **Entrypoint** — the caller (controller, queued job, Artisan command, event listener). No business logic; it gathers input and invokes an action.
2. **Action** — the business logic. One public `handle()`, private helpers, dependencies injected through the constructor.
3. **Persistence** — where state lives: an Eloquent model and/or a repository the action depends on.

The example below processes a **payment refund** and reads as one story from top to bottom.

### Persistence

A repository wraps data access so the action depends on an abstraction rather than reaching for the database directly. It can use Eloquent models internally.

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Refund;

final readonly class RefundRepository
{
    public function findPayment(int $paymentId): Payment
    {
        return Payment::query()->findOrFail($paymentId);
    }

    public function create(Payment $payment, int $amount): Refund
    {
        return $payment->refunds()->create([
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }
}
```

### Action

`ProcessRefund` holds the business rules. It receives the repository, a payment gateway, and **another action** through its constructor; exposes a single public `handle()`; and breaks the work into private helper methods.

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Payment;
use App\Models\Refund;
use App\Repositories\RefundRepository;
use App\Services\PaymentGateway;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ProcessRefund implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private RefundRepository $refunds,
        private PaymentGateway $gateway,
        private NotifyCustomerOfRefund $notifyCustomer,
    ) {
    }

    public function handle(int $paymentId, int $amount): Refund
    {
        $payment = $this->refunds->findPayment($paymentId);

        $this->guardAgainstOverRefund($payment, $amount);

        $refund = $this->refunds->create($payment, $amount);

        $this->gateway->refund($payment->charge_id, $amount);

        $refund->update(['status' => 'completed']);

        // Compose another action to handle the follow-up work.
        $this->notifyCustomer->handle($refund);

        return $refund;
    }

    private function guardAgainstOverRefund(Payment $payment, int $amount): void
    {
        if ($amount > $payment->refundableAmount()) {
            throw new \DomainException('Refund exceeds the refundable amount.');
        }
    }
}
```

### Composing actions ("chaining")

There is no pipeline or `->chain()` helper — you compose actions by having one action call another. Above, `ProcessRefund` received `NotifyCustomerOfRefund` through its constructor and invoked `$this->notifyCustomer->handle($refund)`.

Equivalently, an action can resolve the next one inline with `make()`:

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\RefundProcessed;
use App\Models\Refund;
use Illuminate\Support\Facades\Mail;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class NotifyCustomerOfRefund implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Refund $refund): void
    {
        Mail::to($refund->payment->customer->email)
            ->send(new RefundProcessed($refund));
    }
}
```

Use constructor injection when an action *always* needs its collaborator (it makes the dependency explicit and easy to fake in tests); reach for `OtherAction::make()->handle(...)` for a one-off call.

### Entrypoints

Entrypoints stay thin — they invoke the action and nothing more. The same `ProcessRefund` can be driven from any of them.

**Controller** (constructor injection):

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProcessRefund;
use App\Http\Requests\RefundRequest;
use Illuminate\Http\JsonResponse;

final readonly class RefundController
{
    public function __construct(
        private ProcessRefund $processRefund,
    ) {
    }

    public function store(RefundRequest $request, int $paymentId): JsonResponse
    {
        $refund = $this->processRefund->handle(
            $paymentId,
            $request->integer('amount'),
        );

        return response()->json($refund, JsonResponse::HTTP_CREATED);
    }
}
```

**Queued job** (resolve with `make()` inside `handle()`):

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ProcessRefund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $paymentId,
        public int $amount,
    ) {
    }

    public function handle(): void
    {
        ProcessRefund::make()->handle($this->paymentId, $this->amount);
    }
}
```

**Event listener** (constructor injection):

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\ProcessRefund;
use App\Events\OrderCancelled;

final readonly class RefundCancelledOrder
{
    public function __construct(
        private ProcessRefund $processRefund,
    ) {
    }

    public function handle(OrderCancelled $event): void
    {
        $this->processRefund->handle(
            $event->order->payment_id,
            $event->order->total,
        );
    }
}
```

**Artisan command** (method injection on `handle()`):

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ProcessRefund;
use Illuminate\Console\Command;

final class RefundPaymentCommand extends Command
{
    protected $signature = 'payments:refund {payment} {amount}';

    protected $description = 'Refund a payment.';

    public function handle(ProcessRefund $processRefund): int
    {
        $refund = $processRefund->handle(
            (int) $this->argument('payment'),
            (int) $this->argument('amount'),
        );

        $this->info("Refund #{$refund->id} processed.");

        return self::SUCCESS;
    }
}
```

The same `ProcessRefund` action now powers an HTTP endpoint, a background job, a domain event, and the CLI — with the business logic written exactly once.

## Create An Action

```bash
php artisan make:action ProcessRefund --test
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
