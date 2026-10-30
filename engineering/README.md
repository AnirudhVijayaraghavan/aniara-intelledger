# Engineering standards

This is the current standards index. [ENGINEERING.md](../ENGINEERING.md) explains
the working configurations and incident lessons;
[ENGINEERING_MILESTONES.md](../ENGINEERING_MILESTONES.md) records dated evidence.
The executable configurations below determine actual enforcement. Historical test
counts are evidence of a particular run, not permanent acceptance thresholds.

Use [ADAPTING.md](ADAPTING.md) for another application or stack and
[WALKTHROUGH.md](WALKTHROUGH.md) for the prepared educational syllabus.
The [EQ-11B validation report](VALIDATION.md) is the final status/evidence matrix;
[PORTABILITY.md](PORTABILITY.md) records actual independent skill trials and their
discovery limits.

## Authority and evidence

| Label                     | Meaning                                                                                                                             | What it does not establish                                                         |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| **Automated / mandatory** | An existing configured check or committed CI job must succeed; its probes should demonstrate failure on a representative violation. | Passing checks cannot prove absence of all defects or hosted branch protection.    |
| **Convention / advisory** | A review default, applied in context; explain meaningful deviations.                                                                | A style preference is not a vulnerability or an automatically enforced rule.       |
| **Agent-assisted**        | A bounded review of source, contracts and evidence; findings need concrete references and reasoning.                                | A skill is neither a security boundary nor permission to mutate external systems.  |
| **Manual / readiness**    | A named human verifies a real environment, assistive technology, recovery procedure or repository setting.                          | A proposed runbook, mock or local probe is not completed operational verification. |

User task constraints and applicable AGENTS instructions govern agent work. Keep
Laravel Boost responsible for installed-version framework guidance: inspect
`boost.json`, the repository's AGENTS.md and local Boost MCP
configuration when present. They may be generated/ignored and are not portable
standards artifacts. Use Boost's relevant skill/documentation tools for framework
APIs; if unavailable, disclose that and inspect installed source or official docs.
Do not duplicate or edit Boost-managed instructions to implement these standards.
If a generic example differs from verified project tooling, identify the mismatch
and use the working project contract rather than introducing a second toolchain.

## Mandatory command and CI contract

Read [composer.json](../composer.json), [package.json](../package.json) and
[tests.yml](../.github/workflows/tests.yml) before running an unfamiliar alias.
Run from the repository root with locked dependencies already installed. Checks
can write ignored caches/build output; they must not rewrite maintained source,
development configuration or persistent databases. Execute heavy checks serially.

| Existing command                                         | Scope / required CI job                                                                                                                                               |
| -------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer ci:check`                                      | Safe local aggregate: backend quality, frontend quality, SQLite-isolated Pest. It does **not** aggregate the separate security, browser, PostgreSQL or HTTP E2E jobs. |
| `composer quality:backend`                               | PHPStan, Rector dry-run, Pint check / `backend-quality`.                                                                                                              |
| `composer quality:frontend`                              | Isolated production build, TypeScript, native Node tests, lint/format / `frontend-quality`.                                                                           |
| `composer test:isolation`, then `composer test:isolated` | Configuration-only guard, then serial Pest / `isolated-tests`; includes architecture and safety contracts.                                                            |
| `composer security:check`                                | Locked Composer/npm vulnerability audits / `dependency-security`; requires advisory-service access.                                                                   |
| `composer source:check`                                  | Pinned local Semgrep rules plus failure probes / `source-security`.                                                                                                   |
| `composer secret:check`                                  | Gitleaks current tree, complete history and probes / `secret-security`; full Git metadata required.                                                                   |
| `composer postgres:guard`, then `composer test:postgres` | Configuration-only checks, then explicitly opted-in disposable PostgreSQL / `postgres-integration`. The latter mutates only a verified test lease.                    |
| `composer quality:browser`                               | Synthetic Playwright/axe components across three engines and intentional-failure probes / `browser-accessibility`; no Laravel/database.                               |
| `composer e2e:guard`, then `npm run test:e2e`            | Configuration-only checks, then separately opted-in real HTTP journeys over a disposable lease / `financial-e2e`. Requires built assets/browser/isolated service.     |

The configured final `ci` job requires **all nine** results to equal success,
including failure/cancellation/skip/missing-result handling. Local executable
contracts verify that configuration; the inspected hosted workflow is an older
single-job version, and main is unprotected. See [hosted evidence](VALIDATION.md#hosted-observations-and-bounded-github-hardening-phase).
`composer ci:browser` adds component browsers to the local
aggregate, not the other independent jobs. Never substitute `composer setup`,
write-mode formatters, audit-fix commands or direct persistent migrations.

Dependency/tool installation is a separate, authorized operation. An unavailable
scanner/advisory service/browser or incomplete Git history is **unverified or
failed**, not a clean scan. Do not install missing tools automatically for a
review-only task. PostgreSQL/E2E setup and cleanup belong to the existing guarded
procedures, not a copied shell snippet that exports developer credentials.

## PHP and Laravel

**Automated:** [phpstan.neon](../phpstan.neon) enforces level 8 over its configured
application/bootstrap/config/database/routes paths. Strict Rules enables only
`strictFunctionCalls` and `disallowedLooseComparison`, while the extension's
configuration-default changes also apply. [rector.php](../rector.php) checks its
small allowlist in `app/Data` and `app/Enums`, serially in dry-run mode.
[pint.json](../pint.json) owns formatting. No broad baselines or lowered thresholds.

**Convention:** explicit parameter/return contracts and accurate array shapes;
validate/narrow uncertain values rather than hiding them behind casts or PHPDoc.
New application PHP uses strict declarations unless an inspected framework
boundary justifies an exception. Strict scalar checking depends on the caller;
adding a declaration to a callee alone is not complete coercion protection.
Existing historical files are migrated in bounded, tested groups.

**Evidence:** [strict contracts](../tests/Unit/StrictTypeContractsTest.php),
[quality command contracts](../tests/Unit/QualityCommandContractTest.php),
[typing policy](../ENGINEERING.md#php-type-safety-standard).
PHP is constrained to `~8.4.1`; verify platform requirements and actual CLI/FPM
versions rather than inferring support from a framework name.

## Architecture and testing

**Automated:** [architecture tests](../tests/Unit/ArchitectureTest.php) protect
DTO-to-presentation/persistence boundaries, financial Action-to-presentation
boundaries and model/policy-to-controller direction. Models and Actions may use
Eloquent; DTOs may use enums. Import Actions legitimately accept UploadedFile.
[AST rules](../tests/Support/ArchitectureRules.php) prohibit selected native-float
constructs/division in named financial namespaces and unsafe calls in `App`.
[negative probes](../tests/Unit/ArchitectureRulesTest.php) show forbidden patterns
fail. This is targeted syntax/dependency evidence, not complete dataflow analysis.

**Convention:** keep controllers thin, validation in existing Form Requests,
authorization in policies and focused Actions for domain mutations. Do not add
repositories/services or microservices without a demonstrated boundary. Test
observable contracts and distinct failure modes rather than implementation text.
Reuse [Pest setup](../tests/Pest.php), factories and existing adjacent tests;
configuration tests intentionally inspect configuration contracts.

**Agent-assisted domain review:** trace one actual read/mutation end to end,
including tenant selection, decimal representation, dates, side effects,
idempotency, audit records, locking and rollback. IntelLedger uses Team tenancy,
native-currency decimal strings/Brick Math, independent authoritative balance
observations, append-only corrections and per-currency analytics. These are
application-specific invariants, not a template schema for other products.
Useful examples: [CreateTransaction](../app/Actions/Finance/CreateTransaction.php),
[activity calculator](../app/Actions/Finance/FinancialActivityCalculator.php).

## Database safety and PostgreSQL

**Automated / mandatory:** [TestCase](../tests/TestCase.php) resolves environment
and connection configuration before RefreshDatabase: testing + SQLite `:memory:`
only, including URL resolution and rejection of unsafe connection shapes.
[QualityEnvironment](../tests/Support/QualityEnvironment.php) isolates child
processes without rewriting `.env` or developer caches. Setting phpunit.xml
variables alone is insufficient when inherited URLs or cached config disagree.

PostgreSQL uses the separate
[DisposablePostgreSql](../tests/Support/DisposablePostgreSql.php) owner/OID/nonce/
live-parent receipt, strict endpoint/credential/opt-in checks and bounded cleanup.
[integration runner](../tests/PostgreSqlIntegration.php) verifies migrations,
composite keys, decimals, rollback, contention, source identity and populated
forward upgrades. [FinancialE2EEnvironment](../tests/Support/FinancialE2EEnvironment.php)
reuses that capability and refuses unrelated HTTP servers. No force-drop/adoption.

**Manual:** inspect orphan provenance and cleanup after interruption; never guess
ownership or substitute a development database. Existing probes cover selected
process interruption/rollback, not every network, OS, power-loss or database crash.
Use the [guarded setup/recovery guide](../ENGINEERING.md#eq-07a-disposable-postgresql-integration).

## Authentication and application security

**Automated:** security regression tests exercise request/global-principal
disagreement, guests, incompatible principals, Team authorization and raw-input
boundaries. [AuthenticatedUserResolver](../app/Http/AuthenticatedUserResolver.php)
validates the explicit request principal; it does not authorize a Team.
[input boundary tests](../tests/Feature/Auth/AuthenticationInputBoundaryTest.php)
protect validation before unsafe Fortify normalization. Browser redirects and
JSON 422 responses are different expected contracts.

**Convention / agent-assisted:** trace middleware, signed links, policies, Form
Requests and Actions before changing a security boundary. Preserve intentional
guest access. No global-user fallback, inferred Team actor, ownership escalation
or silent null substitution. Sensitive raw input, file paths, token URLs and
statement contents stay out of logs/default serialization. Review upload
encryption, temporary plaintext, key recovery and retention as separate concerns.

**Manual:** threat-model review, penetration testing, credential revocation,
key-rotation/recovery proof and actual operational access require independent
evidence. Automated tests are not a security certification.

## React, TypeScript and accessibility

**Automated:** [vite.config.ts](../vite.config.ts) owns Oxlint/Oxfmt, React/Hooks
errors and documented generated/vendor exclusions. Owned shadcn/Radix wrappers
remain maintained source. [tsconfig.json](../tsconfig.json) enforces strict typing.
Native [utility tests](../resources/js/lib/accessibility.test.mjs), component
[Playwright configuration](../playwright.config.ts) and
[HTTP E2E configuration](../playwright.e2e.config.ts) protect different layers.
Real axe/keyboard assertions do not replace accessible component contracts.

**Convention:** use [semantic tokens](../resources/css/app.css) and existing
primitives; keep stable label/help/error IDs, combine descriptions with errors,
preserve keyboard reachability/focus restoration and test meaningful interactive
states. WCAG 2.2 AA is the target; prefer native semantics and preserve Radix
behavior. Client money presentation preserves decimal strings; numeric chart
coordinates are not authoritative financial arithmetic. Visual product styling
remains in the local design skill when available, not in these portable standards.

**Manual / limits:** execute the
[screen-reader checklist](../ENGINEERING.md#manual-screen-reader-checklist-not-yet-executed)
with NVDA/Windows and VoiceOver/macOS, native Safari and actual zoom/forced colors.
These verifications remain open. Storybook remains deferred for peer-compatibility
reasons. The documented NavigationMenu scoped axe scan has explicit approval and
keyboard/full-page surrounding checks; it is not permission for broad axe ignores.

## CI, dependencies and supply chain

**Automated:** the workflow uses locked installs, SHA-pinned Actions, read-only
permissions, no persisted checkout credentials, bounded workers/timeouts and
source-integrity checks. [Dependabot](../.github/dependabot.yml) covers Composer,
npm and Actions. Vulnerabilities of all configured severities block lockfile
audits; abandonment is a distinct maintenance signal. Semgrep uses the reviewed
[local rules](../semgrep.yml) and pinned
[Python environment](../semgrep-requirements.txt). Gitleaks uses
[checksummed metadata](../gitleaks.lock.json) and
[reviewed configuration](../.gitleaks.toml). Findings, execution errors and incomplete
scans must fail without printing secrets or uploading source.

**Agent-assisted:** assess production/dev and direct/transitive exposure, advisory
IDs and fix constraints. Prefer targeted, script-disabled minimal updates with
focused regressions. Never run audit-fix/force updates or introduce unreviewed
exceptions. See [triage policy](../ENGINEERING.md#triage-remediation-and-exceptions)
for approvals, expiration and review; installing a scanner does not maintain it.

**Hosted/manual boundary:** EQ-11B inspected read-only GitHub settings and old run
results: native secret scanning/push protection enabled; main unprotected and
Dependabot alerts/security updates disabled. Current hosted workflow execution,
notification/bypass behavior and dependency-review integration remain separate
verification. Settings changes are deferred. Local history remains unavailable;
an explicit remote mirror scan covered 41 commits without supplying workspace
provenance. See [the evidence matrix](VALIDATION.md). Semgrep's Python dependency-
monitoring follow-up remains open; a pin is not vulnerability monitoring.

## Deployment and operational readiness

**Current status:** [EQ-10A ADR](../ENGINEERING.md#eq-10a--aws-preview-architecture-and-operational-design)
is a proposed design only. AWS provisioning is deferred until the core MVP is
ready and a separate plan is authorized. Single-server hosting is not HA.

**Manual / readiness:** verify trusted artifact provenance, runtime/dependency
compatibility, migration identity/permissions and backward compatibility,
worker/scheduler lifecycle, health beyond bootstrap, sanitized monitoring,
private encrypted storage, secret rotation/escrow and actual restore times.
Define and measure RPO/RTO; a backup success message is not restore evidence.
The proposed Lightsail architecture, stored-key tradeoff and cost model are
IntelLedger choices requiring review, not portable defaults. No production,
financial-data readiness or regulatory compliance is claimed.

## Agent-assisted review contract

Choose one requested review scope. Read the matching standard, relevant working
configuration and adjacent regression tests; fetch installed-version framework
guidance through Boost where applicable. Expand to other areas only when an
observed dependency/risk warrants it. Ordinary edits need not activate all reviews.

Default to findings; if the user also authorizes fixes, complete the bounded fixes
and meaningful regression coverage. Respect existing authorization and execution
permissions. Do not install tools, change thresholds, migrate data or provision
resources merely to finish a review. Refuse unsafe database targets without
weakening guards. Lack of a prerequisite is an explicit evidence gap.

For each finding give severity, file/line or exact command, observed behavior,
impact and smallest recommended correction. Separate verified defects, plausible
risks and style advice. State checks actually run, exit results, unavailable
verification and any source/config changes. Sanitize evidence; no raw financial
records, tokens or credentials. A clean review has an explicit inspected scope
and limitations, not an unqualified assertion of safety.

## Codex skills and ownership

Five repository-owned instruction-only skills provide these review workflows:

| Invoke in Codex                     | Purpose                                                                                                                                     |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `$engineering-php-review`           | [PHP/Laravel](../.agents/skills/engineering-php-review/SKILL.md): types, framework boundaries and quality evidence.                         |
| `$engineering-domain-review`        | [Domain architecture](../.agents/skills/engineering-domain-review/SKILL.md): dependencies, tenancy, precision and mutation invariants.      |
| `$engineering-ci-security-review`   | [CI/security](../.agents/skills/engineering-ci-security-review/SKILL.md): command graph, failure behavior and supply-chain controls.        |
| `$engineering-accessibility-review` | [React accessibility](../.agents/skills/engineering-accessibility-review/SKILL.md): semantics, interactions, tokens and evidence.           |
| `$engineering-production-review`    | [Production readiness](../.agents/skills/engineering-production-review/SKILL.md): evidence assessment, with deployment permission separate. |

They live under `.agents/skills/<name>/SKILL.md`; names/descriptions support normal
automatic matching and explicit invocation. No custom agent settings, MCP server,
Composer package or plugin installation is required. A new session may be needed
if the current host does not refresh discovery. Creation/structure validation does
not prove routing or decision quality in a fresh Codex session.
[Official skill discovery/format](https://learn.chatgpt.com/docs/build-skills).

Only these five SKILL.md entrypoints are eligible for version control through
the narrow [.gitignore](../.gitignore) exceptions. Other Boost/local skills and
agent settings remain ignored. Preserve that distinction during Boost updates;
do not append universal rules to AGENTS.md or register these in Boost's managed
skill list. Copy the standards documents **with** the five skills when adapting;
their relative references are intentional. Read [ADAPTING.md](ADAPTING.md) first.

When changing a standard, identify its enforcement source and affected probes,
review the change with its code owner, and update dated evidence. Review skill
metadata/routing and references after moving files or updating tools. New policy
becomes an automated gate only through a separately reviewed executable change;
this documentation milestone adds no CI gate or quality threshold.
