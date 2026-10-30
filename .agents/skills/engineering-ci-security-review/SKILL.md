---
name: engineering-ci-security-review
description: Review CI failure propagation, dependency/source/secret scanning, and supply-chain trust boundaries without changing hosted repository settings.
---

# CI and security review

Read the [command contract](../../../engineering/README.md#mandatory-command-and-ci-contract),
[supply-chain standard](../../../engineering/README.md#ci-dependencies-and-supply-chain)
and [review contract](../../../engineering/README.md#agent-assisted-review-contract).
Use the [adaptation guide](../../../engineering/ADAPTING.md) outside IntelLedger.

Follow actual Composer/npm aliases to their leaves and every required CI job to
the final gate. Look for hidden setup/migrations/write-mode tools, success after
skips/cancellation/missing results, and unbounded workers. Preserve the distinction
between the safe local aggregate and separately guarded/security/browser jobs.

Review triggers, untrusted PR code, shell-expression interpolation, pinned actions,
token permissions, checkout credentials, install scripts and artifact/cache trust.
Inspect scanner versions, rule ownership, coverage/exclusions, redaction and native
failure probes. Differentiate current-tree, full-history and partial scans, and
advisory/network/scanner errors from clean results. Reuse installed package-manager
audits; do not run audit-fix commands or upgrade dependencies during a review.

For findings, record sanitized advisory/rule IDs, severity, direct/transitive and
production/dev exposure, affected constraints and verified fix options. Do not
print detected secrets/snippets or upload source/results to external services.
An exposed real credential requires rotation/revocation assessment before cosmetic
removal. Exceptions need the existing named approval/expiry process, not a blanket
ignore. Framework security questions belong to installed-version Boost guidance.

Use existing isolated failure probes where available; never tamper with maintained
source or hosted settings to demonstrate a failure. Report command exits and the
committed workflow's guarantees separately from actual hosted results, branch
protection, native security features and unavailable Git history.
