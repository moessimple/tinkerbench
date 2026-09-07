---
paths:
  - 'tests/Unit/Requests/**'
---

# Unit Requests

## Asserting a FormRequest's rules() array directly is legitimate here
A `expect(new XRequest()->rules())->toEqual([...])` check against the literal rule array is a sanctioned convention in this project, not an implementation-detail smell. It is the baseline coverage every FormRequest test carries. Rationale follows Jason McCreary's "Test Validation in Laravel with a Form Request assertion" (https://jasonmccreary.me/articles/test-validation-laravel-form-request-assertion): the declared rule set is the contract worth pinning.

Only add `createFormRequest()` (tests/Pest.php) boundary assertions on top when the rule array alone cannot secure a specific edge case: a regex/format rule (SnippetNameRequest alphaDash traversal), conditional/cross-field rules, a custom Rule object, or authorize()/prepareForValidation() logic (UpdateSnippetContentRequest present+nullable + ConvertEmptyStringsToNull). A plain `required|string|max` request (RunSnippetRequest) needs only the array check.
