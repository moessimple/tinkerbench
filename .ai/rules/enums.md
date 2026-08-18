---
paths:
  - 'app/Enums/**'
---

# Enums

## Enums stay plain value types
Enums in app/Enums must not extend, implement, or use anything (no traits, no interfaces besides the backing type). They're plain domain nouns, not behavior carriers, see app.md's naming convention. Enforced by tests/Arch/EnumsTest.php.
