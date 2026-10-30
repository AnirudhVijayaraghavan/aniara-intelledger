---
name: engineering-domain-review
description: Review domain dependencies, tenant isolation, precision, and transactional invariants for a proposed or changed workflow, including financial domains.
---

# Domain architecture review

Read the [architecture standard](../../../engineering/README.md#architecture-and-testing),
[database safety](../../../engineering/README.md#database-safety-and-postgresql)
and [review contract](../../../engineering/README.md#agent-assisted-review-contract).
For a different product, map its domain through the
[adaptation guide](../../../engineering/ADAPTING.md); do not introduce Team,
financial tables or accounting infrastructure just to reuse this review.

Trace one requested workflow from authorized entry point to Action, persistence,
DTO and side effects using actual routes/models/constraints/tests. Identify the
explicit tenant and actor; a mutable preference or globally authenticated user is
not a substitute for resource authorization. Review nested references and foreign
keys together, not policies in isolation. Use Boost for framework-specific APIs.

For money, verify decimal strings, exact arithmetic, scale/rounding, original
currency and asset/liability signs. For any domain, identify immutable history,
correction rules, idempotency identity, audit ownership, lock order, stale tokens
and rollback boundaries. Check exactly-once accepted revisions against retries,
quiet saves and nested transactions where relevant. Separate external-side-effect
compensation from database atomicity; never infer completeness from a subtotal.

Review the existing architecture tests and explain their AST/dependency limits.
Prefer focused existing Actions/DTOs and direct model conventions over new service
layers. Select meaningful failure/real-engine probes only within verified test
isolation. Do not query live financial records or migrate persistent data merely
to support an architecture review.

Report verified invariant violations, concurrency risks and design tradeoffs
separately, with concrete references and the smallest complete recommendation.
Keep business-rule decisions explicit; do not silently redesign the domain while
fixing a type or dependency finding.
