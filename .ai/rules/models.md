---
paths:
  - 'app/Models/**'
---

# Models

## Every *_at column needs an explicit datetime cast
Any model attribute ending in _at (besides the created_at/updated_at timestamps Eloquent casts by default) must declare an explicit 'datetime' cast in casts(), otherwise it comes back as a plain string. Enforced by tests/Arch/ModelsTest.php, which checks every model in app/Models against its factory-built attributes.
