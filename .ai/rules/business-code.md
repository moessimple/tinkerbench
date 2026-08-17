---
paths:
  - 'app/Actions/**'
  - 'app/Support/**'
  - 'app/Enums/**'
---

# Business Code

## No area subfolders, flat per type
Business classes are not grouped into a domain-area subfolder: App\Actions\RunSnippetAction, not App\Actions\Snippets\RunSnippetAction. Each type folder (Actions, Http/Controllers, Http/Requests, Http/Middleware) stays flat as long as it's easy to scan at a glance; reintroduce area subfolders for a type folder once it grows too large or spans too many areas to scan flat.

## Suffix classes by build type
Suffix by type: Action, Controller, Query, Request, Resource, Job. Models, Enums, and Middleware are the exception, no suffix (plain domain noun, e.g. Post not PostModel; EnsureKnownProject not EnsureKnownProjectMiddleware, matching Laravel's own middleware naming and the pest-plugin-laravel arch preset, which has no suffix rule for App\Http\Middleware).

## Every Action/Support/Enum class needs a 1:1 mirrored unit test
Each class in app/Actions, app/Support, app/Enums gets a matching test at the same relative path under tests/Unit/ (app/Actions/RunSnippetAction.php mirrors tests/Unit/Actions/RunSnippetActionTest.php). Mandatory. Enforced by tests/ArchTest.php.

Controllers/Requests/Middleware don't get this mandatory 1:1 mirror: they're proven through tests/Http/ flow tests instead (see tests.md).

The mirrored file only guarantees the class isn't forgotten, not that it's fully covered: tests.md's "every public method gets its own isolated test" is what actually closes that gap, follow it once the test file exists.

## Enums stay plain value types
Enums in app/Enums must not extend, implement, or use anything (no traits, no interfaces besides the backing type). They're plain domain nouns, not behavior carriers, see business-code.md's naming convention. Enforced by tests/Arch/EnumsTest.php.
