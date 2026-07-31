---
name: actions-pattern
description: >-
  Use when creating or refactoring Laravel Actions, Controllers, Jobs,
  Listeners, or Commands that should follow the actions/entrypoints pattern,
  or when using returnearly/actions-pattern.
---

# Actions Pattern

## Overview

Separate business logic from entrypoints. An **Action** is a single-purpose class with one public `handle()`. **Entrypoints** (controllers, jobs, listeners, commands) adapt external input and call the action — they do not own business rules.

## When to Use

- Adding or changing business logic that may be reached from more than one entrypoint
- Thinning a fat controller, job, listener, or command
- Creating a new class with `php artisan make:action`
- Refactoring duplicated logic across HTTP, queue, CLI, or events

## When Not to Use

- Pure plumbing with no domain rules (simple redirects, health checks)
- One-liner wrappers that only call Eloquent with no decisions

## Core Rules

### Structure

- **One public method:** only `handle()` is public
- **Private helpers** for supporting logic
- **Compose actions** by calling other actions (no pipeline/`->chain()` helper)

### Invocation

```php
// Static make() — resolves from the container
ProcessRefund::make()->handle($paymentId, $amount);

// Constructor injection
public function __construct(private ProcessRefund $processRefund) {}
$this->processRefund->handle($paymentId, $amount);
```

### Typing and parameters

- Explicit parameter and return types on `handle()`
- Prefer model instances over bare IDs when the caller already has the model
- Pass DTOs or primitives for request data — never the HTTP `Request`
- Actions must not know about HTTP requests, responses, or other entrypoint-specific I/O

### Naming

- Descriptive and specific: `ProcessRefund`, not `UpdatePayment`
- If naming is hard, the action is probably doing too much

## Package Usage

Install: `composer require returnearly/actions-pattern`

Scaffold: `php artisan make:action ProcessRefund --test`

Every action implements `ActionsPatternInterface` and uses the `ActionsPattern` trait (adds `make()`):

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

Constructor injection only for action dependencies. There is no method injection on `handle()` — pass its arguments explicitly.

### Composing actions

- **Constructor injection** when the collaborator is always needed (explicit, easy to fake in tests)
- **`OtherAction::make()->handle(...)`** for one-off calls

## Entrypoint Checklist

Entrypoints **do**:

1. Receive input for that context
2. Validate (e.g. Form Requests)
3. Look up models from IDs
4. Call the action with typed arguments
5. Format the response for that context

Entrypoints **do not**:

1. Contain business logic
2. Make business decisions
3. Mutate models beyond loading them for the action

| Concern | Belongs In |
|---------|------------|
| Business logic | Action |
| Validation | Entrypoint (Form Request) |
| Authorization | Entrypoint (Policy/Gate) |
| Model lookup | Entrypoint |
| Response formatting | Entrypoint |
| Logging business events | Action |
| Error handling (business) | Action |
| Error formatting (HTTP) | Entrypoint |

## When to Create a New Action

- A second public method would be needed on an existing action
- The same logic is duplicated across entrypoints
- Complex business logic is living on a model or a catch-all service class

## Testing

**Action tests:** full coverage of happy paths, failures, and edges; mock external APIs; test in isolation from entrypoints.

**Entrypoint tests:** validation, authorization, model resolution; mock/spy the action; assert response/output shape.

Adding a new entrypoint only needs: correct inputs prepared, action called with the right args, output formatted correctly.

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Business rules in the controller | Move into an Action `handle()` |
| Passing `Request` into an action | Extract fields / DTO in the entrypoint |
| Multiple public methods on one action | Split into separate actions |
| Action builds JSON / HTTP responses | Return domain data; format in the entrypoint |
| Reaching into the container inside the action | Inject dependencies via the constructor |

## Additional Resources

- Full ProcessRefund walkthrough (persistence, composition, all entrypoints): [examples.md](examples.md)
