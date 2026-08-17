---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## FormRequests expose a semantic getter, not raw input()
Every FormRequest here declares a domain-named getter (e.g. code(), name(), content()) that wraps $this->string('field')->toString(), rather than callers reading $request->input()/dynamic properties or a raw validated() array. Add the same kind of getter for each validated field on a new Request.

## Validation rules use the fluent Rule::string() builder
Validation rules are built with Illuminate\Validation\Rule's fluent string builder (Rule::string()->max(...), ->alphaDash(true)->max(...)), not plain rule strings/arrays like 'string', 'max:100000'. Match this style for new string fields.
