# Architecture guidelines

This application uses a Beyond CRUD layering: `src/Application`, `src/Domain`, `src/Support`. `app/` is
reserved for framework mandatory glue only (service providers, middleware registration), never for new
business logic.

- `src/Domain` holds business rules and state. It knows nothing about HTTP, routing, or how it is reached.
- `src/Application` holds HTTP facing classes that orchestrate Domain classes (controllers, requests,
  resources, middleware). It may depend on Domain, never the other way around.
- `src/Support` holds generic building blocks with no business meaning. Any layer may depend on it.
- Namespace domain first, not type first: `Domain\{Area}\Actions`, not `Domain\Actions\{Area}`.
- Suffix classes by their build type: Action, Controller, Query, Request, Resource, Job, Middleware. Models
  and Enums are the exception, they carry no suffix (a plain domain noun, for example `Post`, not `PostModel`).
- Every class in `src/` gets a 1:1 mirrored test class in `tests/` (for example
  `src/Domain/Billing/Actions/CreateInvoiceAction.php` mirrors
  `tests/Domain/Billing/Actions/CreateInvoiceActionTest.php`). This is mandatory. `tests/Architecture` is the
  only exception, since it checks relationships across all layers instead of mirroring a single class.

@boostsnippet('Example action class', 'php')
<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

final readonly class CreateInvoiceAction
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function execute(Order $order): Invoice
    {
        return $this->invoices->createFor($order);
    }
}
@endboostsnippet
