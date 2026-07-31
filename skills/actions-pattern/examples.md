# ProcessRefund Walkthrough

Organize a feature into three layers:

1. **Entrypoint** — controller, queued job, Artisan command, or event listener. No business logic; gathers input and invokes an action.
2. **Action** — business logic. One public `handle()`, private helpers, dependencies via the constructor.
3. **Persistence** — Eloquent model and/or repository the action depends on.

## Persistence

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

## Action

`ProcessRefund` holds the business rules. It receives the repository, a payment gateway, and another action through its constructor.

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

## Composing actions

There is no pipeline helper — compose by calling another action. Constructor injection (above) when the collaborator is always needed; otherwise:

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

## Entrypoints

The same `ProcessRefund` powers HTTP, queue, events, and CLI.

### Controller

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

### Queued job

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

### Event listener

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

### Artisan command

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
