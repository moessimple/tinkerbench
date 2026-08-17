---
paths:
  - 'tests/Http/Controllers/**'
---

# Controllers

## Mock SnippetRepository in every controller test that depends on it
Every controller test whose controller depends on App\Support\SnippetRepository mocks it with $this->mock() (single expectation) or $this->mock(SnippetRepository::class, function (MockInterface $mock) {...}) when a controller calls more than one repository method (e.g. OpenSnippetController's ensureExists()+contents()). None use Storage::fake('snippets'); that belongs only in SnippetRepositoryTest itself. Controllers that don't depend on SnippetRepository at all (e.g. RunSnippetController, which depends on RunSnippetAction instead, or any Projects controller) mock whatever they do depend on, following the same "mock only collaborators with their own test" rule.

Why: SnippetRepository already has full, dedicated test coverage (SnippetRepositoryTest), so a controller test only needs to prove its own responsibility, delegating with the right arguments and shaping the response/Inertia props correctly from whatever the repository returns. Re-touching real storage in every controller test re-proved behavior SnippetRepositoryTest already owns (e.g. "creates missing snippet with default content" is ensureExists()'s own behavior, not the controller's).

How to apply: when adding a new Snippets controller that depends on SnippetRepository, mock it the same way; assert only the controller's own branching (status codes, response shape) driven by the mocked return value, not storage side effects.

## Split delegation wiring from response shaping in controller tests
Once a controller's collaborator (Support class, Action, FormRequest, Middleware) already has its own complete test elsewhere, don't re-prove its behavior here, only prove the controller's own responsibility: that it delegates correctly, and that it shapes the response correctly from whatever the collaborator returns.

Split these into separate tests, don't blend them:
- One dedicated wiring test per collaborator: mock with strict `->once()->with(...)`, assert nothing about the response. This is the delegation proof.
- One test per branch/outcome: mock loosely (`->andReturn(...)` only, no `once()`/`with()`), assert the resulting status code and JSON body. This is the response-shaping proof.

Reference examples: RunSnippetControllerTest.php (uses the right action) and UpdateSnippetNameControllerTest.php (uses the right repository) vs. their respective response-branch tests. Skip the split only when there is no separate collaborator to mock (e.g. pure routing logic embedded directly in the controller); in that case the controller test is the only place proving that behavior and should cover it fully.

## Every controller test proves its own FormRequest with toUseFormRequest()
Every Http controller test whose controller declares a FormRequest parameter (not the base Illuminate\Http\Request) must include an `it('uses the right request', ...)` test asserting `expect(Controller::class)->toUseFormRequest(TheRequest::class)`. Mirrors the existing mandatory middleware rule above, `toUseMiddleware()` is required for every route middleware, `toUseFormRequest()` is required whenever the controller takes a FormRequest. Controllers with no FormRequest (e.g. ListProjectsController, DeleteSnippetController, StartLanguageServerController) need no such test.

## Skip re-testing a decision made entirely outside the controller
The line isn't "wiring vs. response", it's who makes the decision. Always test a response branch the controller itself builds from a collaborator's result (e.g. UpdateSnippetNameController mapping RenameSnippetResult to 200/404/409), that's the controller's own job and its test is the only place proving it.

Skip re-testing a branch that something else decides entirely before the controller ever runs, when that something already has both a wiring proof pointing at this exact route (toUseMiddleware(), toUseFormRequest()) and its own dedicated test proving the behavior. Example: EnsureKnownProject aborts unknown-project requests with a 404 before ListSnippetsController/StartLanguageServerController even execute; that path is already proven by EnsureKnownProjectTest, re-testing "rejects a project unknown to herd" in the controller test would just be a second copy of that same proof, not a test of the controller's own response shaping.
