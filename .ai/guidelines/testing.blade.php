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
- Keep one behavior per test. A test that mocks a collaborator to prove delegation happened and a test that
  proves the actual HTTP response are two different claims, do not blend them into one test with one assertion
  that only covers half of what the test name promises.
- Name tests with plain words, not pattern jargon. Prefer "uses" over "delegates", and generally the plainest
  verb that stays accurate over pattern-language terms (delegation, orchestration, composition, ...).

@boostsnippet('Action test proves the real behavior, controller tests split delegation from response', 'php')
// tests/Domain/Billing/Actions/CreateInvoiceActionTest.php
it('creates an invoice for the order', function () {
    $order = Order::factory()->create();

    $invoice = app(CreateInvoiceAction::class)->execute($order);

    $this->assertDatabaseHas('invoices', ['order_id' => $order->id]);
});

// tests/Application/Billing/Controllers/CreateInvoiceControllerTest.php
// proves only that the controller uses the already-tested action, nothing about the response
it('uses CreateInvoiceAction', function () {
    $order = Order::factory()->create();

    $this->mock(CreateInvoiceAction::class)
        ->shouldReceive('execute')->once()->with($order)->andReturn(new Invoice(['order_id' => $order->id]));

    $this->postJson("/orders/{$order->id}/invoices");
});

// proves the actual observable HTTP response, no mock, the real action runs
it('returns the created invoice', function () {
    $order = Order::factory()->create();

    $this->postJson("/orders/{$order->id}/invoices")
        ->assertCreated()
        ->assertJsonPath('order_id', $order->id);
});
@endboostsnippet
