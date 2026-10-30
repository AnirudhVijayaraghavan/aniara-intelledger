---
name: engineering-php-review
description: Review PHP/Laravel changes for type safety, framework boundaries, and check-only quality evidence when a bounded engineering review is requested.
---

# PHP/Laravel quality review

Read the [review contract](../../../engineering/README.md#agent-assisted-review-contract)
and [PHP standard](../../../engineering/README.md#php-and-laravel). If adapting to
another app, first use the [adaptation guide](../../../engineering/ADAPTING.md).

Inspect the requested diff/workflow, installed versions, nearby code/tests and
actual PHPStan, Pint, Rector and strict-type scopes. Use Laravel Boost's available
framework skills/documentation for version-specific APIs; otherwise inspect
installed source/official docs and disclose the unavailable integration. Do not
copy its maintained guidance or treat IntelLedger paths as universal defaults.

Focus on uncertain input shapes, nullable relationships/timestamps, principal
contracts and framework overrides. Validate or narrow real values; distinguish
optional nulls from invariant failures. Check strict scalar behavior at callers,
serialization and resource/inherited method boundaries. Do not hide findings with
PHPDoc assertions, casts, broad ignores or a lower analysis level.

Select existing check-only aliases and the smallest meaningful regressions. Verify
the effective test target before database-backed checks; retain bounded serial
execution. A review does not authorize package installation, global formatting,
bulk strict declarations or automatic refactoring. If fixes are also requested,
preserve financial semantics and authorization and validate the changed boundary.

Report actionable findings with file/line, impact and evidence; distinguish type
errors, real runtime/security defects and advisory style. State exact checks run
and evidence gaps. A passing analyzer is not proof of complete runtime safety.
