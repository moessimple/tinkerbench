---
paths:
  - 'app/Enums/**'
---

# Enums

## Enums stay plain value types
Enums in app/Enums must not extend, implement, or use anything (no traits, no interfaces besides the backing type). They're plain domain nouns, not behavior carriers, see app.md's naming convention. Enforced by tests/Arch/EnumsTest.php.

## Result enums for multi-outcome operations
When a Support/repository method has more than one distinct failure mode, model the outcome as a dedicated unbacked enum here (success case plus one case per failure, e.g. RenameSnippetResult: Renamed/Missing/Conflict/Failed). The method returns the enum; the controller maps each case to an HTTP status with abort_if(). Single pass/fail operations stay bool and get no enum.

## Enums are unbacked unless the value is used
Declare enums without a backing type. Add : string / : int only when the scalar value is actually consumed outside the enum, as Disk does (its value is the filesystem disk name passed to Storage::disk()). The result enums stay unbacked.
