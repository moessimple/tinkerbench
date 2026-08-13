---
paths:
  - 'tests/Application/Snippets/Controllers/**'
---

# Controllers

## Mock SnippetRepository in every controller test that depends on it
Every Application\Snippets\Controllers test whose controller depends on Support\SnippetRepository mocks it with $this->mock() (single expectation) or $this->mock(SnippetRepository::class, function (MockInterface $mock) {...}) when a controller calls more than one repository method (e.g. OpenSnippetController's ensureExists()+contents()). None use Storage::fake('snippets') anymore, that belongs only in SnippetRepositoryTest itself. Controllers that don't depend on SnippetRepository at all (e.g. RunSnippetController, which depends on RunSnippetAction instead) mock whatever they do depend on, following the same "mock only collaborators with their own test" rule.

Why: SnippetRepository already has full, dedicated test coverage (SnippetRepositoryTest), so a controller test only needs to prove its own responsibility, delegating with the right arguments and shaping the response/Inertia props correctly from whatever the repository returns. Re-touching real storage in every controller test re-proved behavior SnippetRepositoryTest already owns (e.g. "creates missing snippet with default content" is ensureExists()'s own behavior, not the controller's).

How to apply: when adding a new Snippets controller that depends on SnippetRepository, mock it the same way; assert only the controller's own branching (status codes, response shape) driven by the mocked return value, not storage side effects.
