# Testing guidelines

- Test level (unit vs feature/HTTP) does not matter as long as the observable behavior is covered end to end.
  Prefer unit tests for Domain classes, add feature/HTTP tests where routing, serialization, or the interaction
  of several classes needs proving.
- Assert behavior and observable effects (return values, persisted state, dispatched events/jobs, HTTP
  responses), not implementation details (no private methods, no internal call order, unless that delegation
  itself is a documented contract).
- Once a class has its own complete test, fake or mock it in its callers' tests instead of re-proving its
  behavior there. A caller's test only proves the caller's own responsibility (delegation, wiring, its own
  transformation).

@boostsnippet('Action test proves the real behavior, controller test mocks the already-tested action', 'php')
// tests/Domain/Billing/Actions/CreateInvoiceActionTest.php
it('creates an invoice for the order', function () {
    $order = Order::factory()->create();

    $invoice = app(CreateInvoiceAction::class)->execute($order);

    $this->assertDatabaseHas('invoices', ['order_id' => $order->id]);
});

// tests/Application/Billing/Controllers/CreateInvoiceControllerTest.php
it('delegates to CreateInvoiceAction and returns the created invoice', function () {
    $order = Order::factory()->create();
    $this->mock(CreateInvoiceAction::class)
        ->shouldReceive('execute')->once()->with($order)->andReturn(new Invoice(['order_id' => $order->id]));

    $this->postJson("/orders/{$order->id}/invoices")->assertCreated();
});
@endboostsnippet
