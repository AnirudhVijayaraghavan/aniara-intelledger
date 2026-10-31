---
name: engineering-production-review
description: Assess operational and production readiness from runtime, release, storage, identity, monitoring, and recovery evidence; assessment does not authorize deployment.
---

# Production-readiness assessment

Read the [readiness standard](../../../engineering/README.md#deployment-and-operational-readiness)
and [review contract](../../../engineering/README.md#agent-assisted-review-contract).
For another application, first use the
[adaptation guide](../../../engineering/ADAPTING.md). IntelLedger's AWS ADR is a
proposed profile, not a required hosting architecture or completed deployment.

Inspect actual runtime/extension/SSR requirements, private file and temporary paths,
queue/scheduler behavior, mail/auth origins, secrets/key dependencies, schema
permissions and health contracts. Use Boost for framework-specific deployment
behavior; verify current provider capabilities/prices from official sources when
the requested assessment depends on them. Label estimates and assumptions.

Trace immutable artifact provenance, locked builds, environment-specific caches,
separate migration identity, backward-compatible schema changes, worker restart,
readiness, rollback and restore. Distinguish app/control-plane identities, workload
credentials, private storage, encryption and offline key recovery. A private
directory is not encryption; backups beside keys or on the same host are not
independent recovery. A single server is not HA.

Review sanitized logs, bounded metrics/alarms, queue progress, backup freshness,
actual restore evidence, retention, incident ownership and RPO/RTO. Separate
implementation/tests from real environment proof and proposed runbooks. Evaluate
the smallest architecture that meets requirements; avoid services solely for
resume value. Do not assume AWS, Team tenancy, a particular DB or financial rules
when assessing a different product.

Report blockers, accepted risks requiring a named decision, estimated costs and a
bounded readiness plan. Do not provision, deploy, rotate credentials, restore data
or change repository settings as a side effect of assessment. For IntelLedger,
AWS activation stays deferred until core MVP readiness and separate authorization.
