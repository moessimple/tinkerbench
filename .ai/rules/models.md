---
paths:
  - 'app/Models/**'
---

# Models

## Every *_at column needs an explicit datetime cast
Any model attribute ending in `_at` (besides the `created_at`/`updated_at` timestamps Eloquent casts by default) must declare an explicit `'datetime'` cast in `casts()`, otherwise it comes back as a plain string and breaks the first Carbon call on it. Match `email_verified_at` in `User` (covered by `tests/Unit/Models/UserTest.php`); add the cast and a matching test for any new `_at` attribute.
