# IntelLedger engineering evidence ledger

This is the concise index for the final engineering walkthrough. Detailed policies,
exceptions, commands, and source links live in [ENGINEERING.md](ENGINEERING.md).
Historical evidence below comes from the recorded milestones and checked-in tests;
it does not assert that a hosted GitHub run occurred. Reproduce local evidence with
the named commands. Runtime, application, and tool versions remain in the lockfiles.

| Milestone / purpose                      | Tools and important files                                                                                                                                      | Enforced command / job; evidence                                                                                                                                                                                                                | Walkthrough concept / decision                                                                                                                                                                                  |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| EQ-01 / 01.1: deterministic CI baseline  | Composer scripts, `.github/workflows/tests.yml`, `QualityEnvironment`, unchanged `tests/TestCase.php`; `.codex/config.toml` formatting correction              | `ci:check` delegates serial backend, frontend, isolated-test phases; independent jobs feed `ci`. Configuration-only tests precede database tests.                                                                                               | Locked installation versus application initialization; check-only formatting; exit-code propagation; process-only environment isolation.                                                                        |
| EQ-02: all owned frontend source checked | Vite Plus 1.3.0, TypeScript 5.9.3, React 19.3.1, Inertia React 3.9.1; `vite.config.ts`, native utility tests                                                   | `quality:frontend` / `frontend-quality`: build, types, 23 Node utility tests, Oxlint/Oxfmt. Owned UI primitives included; generated Wayfinder output excluded.                                                                                  | Source ownership, React/Hooks checks, strict types, legitimate generated-code exclusions, non-mutating failure probes.                                                                                          |
| EQ-03A–B8: global level 8                | PHPStan 2.3.3, Larastan 3.13.2; `phpstan.neon`, `AuthenticatedUserResolver`, financial DTOs/Actions and authentication regression tests                        | `types:check` / `backend-quality`: complete configured application scope at level 8; zero diagnostics, no pilot/baseline suppressions.                                                                                                          | Runtime narrowing versus invented annotations; request principal versus global user; nullable domain contracts and exact decimal strings.                                                                       |
| EQ-03C: selective strict rules           | Strict Rules 2.1.0; explicit PHPStan flags                                                                                                                     | `types:check`: strict function calls and loose comparisons enforced; remaining rules deliberately deferred.                                                                                                                                     | Safe comparison semantics and extension defaults; incremental policy adoption.                                                                                                                                  |
| EQ-03D: runtime and strict declarations  | PHP `~8.4.1`; local PHP 8.4.2, CI 8.4; scoped financial DTO/enum declarations                                                                                  | Composer/platform checks, scoped strict-type checks, existing quality gate. No claim of tested PHP 8.5 support.                                                                                                                                 | Caller-dependent scalar coercion, framework boundaries, gradual migration instead of bulk declarations.                                                                                                         |
| EQ-04: dependency direction              | Pest 5.3.4 / Architecture 5.0.0; `tests/Support/ArchitectureRules.php`, architecture tests                                                                     | Complete isolated Pest suite includes dependency/debug/strict-type rules and forbidden-dependency probes.                                                                                                                                       | DTO purity, Actions versus presentation, model/policy dependency direction, targeted AST precision checks and limits.                                                                                           |
| EQ-05: conservative modernization        | Rector 2.8.1, Pint 1.32.1; `rector.php` scoped to `app/Data`, `app/Enums`                                                                                      | `rector:check`, `lint:check` / `backend-quality`: dry-run baseline, one worker, bounded memory, no source rewrites.                                                                                                                             | Reviewed allowlists versus broad automatic sets; separate modernization, formatting, and behavior verification.                                                                                                 |
| EQ-06A / A2: Fortify input boundaries    | Fortify 1.41.0; early email validation middleware, provider pipeline, auth/security tests                                                                      | Isolated Pest suite: malformed inputs rejected by standard validation; valid normalization, login/reset/2FA behavior retained.                                                                                                                  | Validate shape before normalization; browser versus JSON responses; throttle placement; authentication boundary regression tests.                                                                               |
| EQ-06B: dependency security              | Native Composer/npm audits; `.github/dependabot.yml`, dependency-security contract tests                                                                       | `security:check` / mandatory `dependency-security`; synthetic advisory/outage probes. Baseline audits recorded clean; warning and blocking policies distinguished.                                                                              | Direct/transitive and production/dev exposure; service failures are not clean reports; exception expiry and operational triage.                                                                                 |
| EQ-06C1: local SAST                      | Semgrep CE 1.182.1, Python 3.11, `semgrep.yml`, `semgrep-requirements.txt`, source-security runner/probes                                                      | `source:check` / mandatory `source-security`: 15 local rules, paired vulnerable/safe probes, sanitized output and coverage enforcement.                                                                                                         | Taint sources/sinks, parameter binding, false positives, CE dataflow limits, no source-upload service.                                                                                                          |
| EQ-06C2: secrets and supply chain        | Gitleaks 8.30.1, `gitleaks.lock.json`, `.gitleaks.toml`, native secret probes and workflow contracts                                                           | `secret:check` / mandatory `secret-security`; full-history checkout, checksum/version checks, distinct finding/error exits. One historical unusable sample has a narrow tested exception. EQ-06 baseline: 1,593 Pest tests / 11,307 assertions. | Current tree versus history; rotation before history remediation; immutable pins, least privilege, untrusted PR/cache/artifact boundaries.                                                                      |
| EQ-07A: real PostgreSQL integration      | PostgreSQL 18.6 native locally; `18.6-bookworm` service pinned by digest; `DisposablePostgreSql`, `PostgreSqlIntegration.php`, `PostgreSqlEnvironmentTest.php` | `postgres:guard`: 41 configuration-only tests. `test:postgres`: 6 real checks / 22 assertions, owned-database cleanup verified. New mandatory `postgres-integration`; existing `ci:check` remains PostgreSQL-free.                              | Dedicated endpoint and role, effective configuration, pre-provider guard, random database/OID/nonce ownership, savepoints, SQLSTATEs, NUMERIC precision, cleanup without adopting/resetting existing databases. |

## Reproduction and outstanding verification

EQ-07B extends the existing EQ-07A row and command rather than creating a second
test framework:

| Milestone / purpose                                         | Tools and important files                                                                                                                                                      | Enforced command / job; evidence                                                                                                                                                                                           | Walkthrough concept / decision                                                                                                                                                                                                                    |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| EQ-07B: contention, populated upgrades, interrupted workers | PostgreSQL 18.6; locked Symfony Process 8.1.9; `DisposablePostgreSql`, `PostgreSqlIntegration.php`, `PostgreSqlWorker.php`, `PostgreSqlScenarios`, `PostgreSqlEnvironmentTest` | Existing mandatory `postgres-integration` / `composer test:postgres`; 18 checks / 114 assertions per successful run. Configuration-only `postgres:guard`: 55 tests / 96 assertions. Final repeatability/CI evidence below. | Observed blocking versus sleeps; lock serialization versus stale tokens; unique-index arbitration; savepoint versus process-exit rollback; actual populated migration prefixes; OID/nonce capabilities; refusal versus automatic orphan adoption. |

- EQ-07A final local `composer ci:check` passed: **1,639 Pest tests / 11,434
  assertions**, PHPStan, Rector, Pint, TypeScript, all **23 Node tests**, frontend
  lint/format, and production build. PostgreSQL was stopped during this run.
- Ordinary local baseline: `composer ci:check`. PostgreSQL safety tests are included
  without a PostgreSQL connection. PostgreSQL checks use the separate explicit
  opt-in command; no production configuration or the SQLite guard is changed.
- Security gates remain separate mandatory jobs: `security:check`, `source:check`,
  `secret:check`. A metadata-free snapshot cannot pass the full-history secret check;
  tree scans and explicit mirror scans must be reported separately.
- EQ-07A evidence includes successful forward migrations, native FK/uniqueness
  rejections, exact decimals, audit rollback, and unchanged observations. The first
  Windows memory failure happened before PHP startup; the resource-bounded retry
  passed. Read-only catalog checks returned zero owned databases/zero bootstrap
  tables; the separately owned cluster was stopped and removed.
- Hosted PostgreSQL container startup/health/networking, the seven-job final gate,
  and branch protection require an actual GitHub run after commit/push. The earlier
  repository audit found `main` unprotected and Dependabot alerts/security updates
  disabled. Dependency-review APIs returned 403/404, so no unavailable job was added.
  Reverify settings; do not silently claim these repository controls are enabled.
- EQ-07B retains the same mandatory job, service image, and SQLite isolation guard.
  Independent workers verify competing standalone/transfer reversals, stale tokens,
  unique source identities after commit/rollback, exact audit counts, injected
  second-leg failure, and terminated-child rollback. A real pre-import migration
  prefix is populated before forward import migrations; existing rows and audit
  snapshots must remain unchanged. See ENGINEERING.md for synchronization limits.
- Final EQ-07B evidence: **two consecutive final-code PostgreSQL 18.6 runs**, each
  **18 checks / 114 assertions**, after three earlier successful repetitions.
  Catalog inspection returned **0 run databases / 0 run sessions / 0 bootstrap
  tables**. The separately initialized native cluster was stopped and removed;
  **0 private clusters / 0 private bootstrap directories / 0 port-65432 listeners**
  remained. Validation used a separate native cluster.
- Configuration-only guards passed **55 tests / 96 assertions**; unchanged SQLite
  isolation passed **26 tests / 74 assertions**. Full `composer ci:check` exited
  **0**: **1,653 Pest tests / 11,451 assertions**, zero PHPStan/Rector findings,
  Pint, TypeScript, all **23 Node tests**, lint/format, and production build.
  PostgreSQL was stopped. Hashes of **512 monitored source/protected files** were
  unchanged by validation (Git metadata is unavailable in this workspace).
- The first sandboxed full-CI attempt stalled in an existing native dependency-audit
  fixture's Windows process cleanup. Its exact Composer/Pest/server ancestry was
  verified before stopping only that validation tree; its synthetic temporary
  fixture was removed. The unchanged full command then passed with permitted
  process termination. No tests, thresholds, or isolation protections were weakened.
  The initial stdin-based PostgreSQL worker barrier also failed on Windows and was
  replaced with the bounded advisory-lock barrier before final repeatability runs.
- Follow-ups: pinned Python dependency auditing/monitoring for Semgrep; reviewed
  PostgreSQL image updates; exhaustive deadlock/network/server-crash/restore testing;
  hosted Linux termination verification; browser/accessibility/component testing.

For the educational walkthrough, trace one change through **contract → guard →
negative probe → positive real-engine test → check-only command → required CI gate**.
Keep demonstrated properties separate from tool heuristics and hosted assumptions.

## EQ-08A: Storybook activation deferred

**User decision: defer activation and preserve every existing constraint.**
Only this ledger and ENGINEERING.md retain changes. No Storybook dependencies,
configuration, stories, scripts, or mandatory CI integration are shipped.

- Purpose: isolate existing React components and document the same design system.
  The guide now inventories all 26 UI primitives, actual Tailwind tokens/typography,
  Radix provider/portal requirements, and a five-component initial story plan.
- Reviewed versions: Storybook 10.6.5 requires an older optional Vite Plus peer;
  10.3.5 builds with the existing alias but has critical/high advisories;
  patched 9.1.21 excludes Vite 8; 11.0.0-alpha.8 also excludes Vite Plus 1.x.
  Existing React 19.3.1, Vite Plus 1.3.0 (bundled Vite 8.3.4), Tailwind 4.3.3,
  and strict TypeScript 5.9.3 are retained.
- Exploratory evidence: exact 10.3.5 package installation without forced peers;
  one React/Vite implementation; five-component stories built independently of
  PHP, authentication, databases and Wayfinder; TypeScript/lint passed. Audit
  rejection prevented activation. Browser/keyboard/axe checks were **not run**.
- Recovery: restored the original manifests/384-entry lock graph, removed all
  exploratory code/assets, restored Babel 8.0.9, and obtained a clean original
  dependency audit. No security thresholds, overrides, or exclusions were added.
- Commands/jobs: the existing CI graph is unchanged. No non-working Storybook
  command or vulnerable dependency was made mandatory.
- Final validation (2026-10-29): restored npm lockfile audit **0 vulnerabilities**;
  SQLite isolation **26 tests / 74 assertions**; `composer ci:check` exit **0**,
  **1,653 Pest tests / 11,451 assertions**, all **23 Node tests**, PHPStan,
  Rector, Pint, strict TypeScript, lint/format and production build passed.
  Hashes of **513 monitored source/protected files** were unchanged by validation.
  Compared with the preceding code baseline, only the two engineering documents
  differ. No browser/addon success or completed Storybook activation is claimed.
- Outstanding: a patched, peer-compatible release; reproducible locked install;
  isolated build/global-settings checks; actual browser/addon findings; required
  frontend build integration. EQ-08B must unblock compatibility first; broader
  accessibility remediation and Playwright remain EQ-09.
- Walkthrough: build success versus security/peer compatibility, optional-peer
  constraints, synthetic component fixtures, semantic token ownership, portalled
  theme inheritance, and honest evidence boundaries. See ENGINEERING.md for the
  advisory links and detailed activation contract.

## EQ-09A: Component accessibility hardening

- Purpose: keyboard-operable password visibility, meaningful transaction error
  associations, and measured text/control contrast without changing financial
  behavior or installing Storybook/testing dependencies.
- Source: `password-input.tsx`, `transaction-fields.tsx`, `financial-choice.tsx`,
  `input-error.tsx`, shared UI Button/Input/Badge/Checkbox/Select/Alert/Dropdown
  Menu, and `resources/css/app.css`. Only presentation/accessibility contracts
  changed; auth protocols, routes, data and the SQLite guard remain untouched.
- Tools: existing React/React DOM 19.3.1, Vite Plus 1.3.0 (Vite 8.3.4), TypeScript
  5.9.3, Tailwind 4.3.3, native Node tests; local Chrome 156.0.8078.48. Package
  manifests/lockfiles and existing quality/security commands remain unchanged.
- Enforced coverage: `accessibility.test.mjs` adds 13 actual React SSR/contrast
  tests through the existing Node glob; `react-components.mjs` provides isolated
  temporary TSX compilation. The main frontend graph builds generated routes
  before running them. No source-text-only component behavior tests or DOM shim.
- Evidence: original keyboard/association defects reproduced; original contrast
  was 2.168:1 for teal links, 1:1 for light destructive alerts, and 2.427:1 for
  dark destructive buttons. Corrected values are 6.100, 5.661 and 6.528:1.
  Detailed surfaces, states, boundary/focus ratios and criteria are in ENGINEERING.md.
- Browser: real native keyboard, accessibility-tree names/descriptions, computed
  normal/hover/focus colors and screenshot inspection on synthetic fixtures.
  A placeholder hover regression caught during token adjustment was corrected
  using existing muted/secondary fill roles; no failing threshold was relaxed.
  Final browser/CI results and cleanup are recorded below after validation.
- Decisions: separate brand fill from accessible text; distinguish filled and
  unfilled destructive roles; retain help plus error IDs; preserve legacy tab
  order without redesigning auth forms; use opaque focus rings. Color arithmetic
  is test-only sRGB measurement, not financial arithmetic.
- Remaining: Storybook's unchanged compatibility/security blocker; broader
  primitive/focus/error-announcement checks; real assistive technology, zoom,
  mobile and cross-browser verification; reproducible browser/axe CI in EQ-09B.
  Automated contracts and this local probe do not establish full WCAG compliance.
- Final evidence (2026-10-29): **13/13 new component/contrast tests**, **36/36
  total Node tests**, and **55 Chrome checks** passed. Browser assertions covered
  genuine hover/focus states, native Tab/Enter/Space, accessibility-tree names and
  combined descriptions, preserved password input identity/attributes, and no
  toggle submission. Browser profiles and generated SSR module directories were
  both **0** after cleanup; only ignored synthetic probe/results/screenshots remain.
- `composer ci:check` exited **0**, with **1,653 Pest tests / 11,451 assertions**,
  PHPStan, Rector, Pint, strict TypeScript, frontend lint/format and production
  build passing. SQLite isolation separately passed **26 tests / 74 assertions**.
  Hashes of **515 monitored source/protected files** were unchanged by validation.
  Package manifests/locks, database configuration, backend auth/API/financial code,
  TestCase and CI command/workflow definitions remained unchanged by implementation.
- Additional security checks: Semgrep **1.182.1**, **451 files / 0 findings**;
  Gitleaks **8.30.1**, **480-file available-source snapshot / 0 findings**. The
  sandbox could not initially verify the cached Semgrep executable; the unchanged
  pinned scan passed with normal Windows execution permissions. Git metadata is
  absent, so this was not a new full-history secret scan. No thresholds/exceptions
  were relaxed and no packages were installed.

### EQ-09B1 — Playwright and accessibility CI (2026-10-29)

- Purpose: reproducible component keyboard/axe checks independent of Laravel,
  generated routes, authentication and financial databases; Storybook stays deferred.
- Tools: exact `@playwright/test` 1.64.1 and `@axe-core/playwright` 4.14.0,
  locked axe-core 4.14.0; Chromium 156.0.8078.4 revision 1248. Only five packages
  added; all existing locked versions/integrities preserved.
- Sources: `playwright.config.ts`, `tests/Browser` synthetic fixture/scenarios,
  `tests/Support/browser-harness.mjs`, failure-probe runner, npm/Composer aliases,
  strict TypeScript includes, workflow and quality/status-gate contract tests.
- Enforcement: `npm run test:browser`, `npm run test:browser:probe`, opt-in
  `composer quality:browser` / `composer ci:browser`. Browser-free `ci:check`
  remains unchanged. New mandatory `browser-accessibility` job joins the final
  eight-job gate, without PHP/database setup or result uploads.
- Decisions: actual components/tokens; one worker, zero retries, bounded timeouts;
  loopback-only preview with no reuse or environment loading; fail on unexpected
  requests; fonts/animation completion before scans. Open Radix menu/listbox
  scopes are reviewed against axe's modal heuristic and backed by real Tab and
  programmatic background-focus containment assertions; no disabled rules.
- Education: SSR vs browser behavior; accessibility names/descriptions; modal
  heuristics vs actual focus traps; 200% equivalent reflow vs device pixel ratio;
  synthetic violation vs tooling failure; package pins vs browser revisions;
  required-job failure propagation and automatic vs manual WCAG evidence.
- Remaining hosted evidence: Linux Chromium installation/execution, required CI
  status settings and actual hosted eight-job gate. Screen readers, other browser
  engines, full pages and remaining primitives belong to EQ-09B2. See ENGINEERING.md
  for setup, scope, interpretation, exclusions and safety details.
- Separate audit finding: npm's full lockfile audit is clean. The unchanged
  Composer graph now reports **PKSA-wtxr-p26d-nn42 / CVE-2026-45070**, medium,
  in production-transitive `symfony/mime` **8.1.7** (email header injection via
  non-token MIME parameter names; advisory lists 8.1.10 as the fixed 8.1 release).
  `composer security:check` correctly exits **1** and the required dependency job
  remains blocking. No unrelated PHP upgrade or exception was applied. Triage
  application exposure and perform a targeted, separately validated update;
  browser/quality success does not resolve this security gate.
- Final local validation: `composer ci:check` exited **0** with **1,659 Pest
  tests / 11,564 assertions**, PHPStan level 8 + Strict Rules, Rector dry-run,
  Pint check, TypeScript, lint/format, **36 Node tests** and production build.
  Configuration-only SQLite isolation separately passed **26 tests / 74
  assertions**. Focused workflow/security contracts passed **55 tests / 716
  assertions**, including executable final-gate failure cases for the new job.
- Browser evidence: final JSON run **14 passed / 0 skipped / 0 flaky / 0
  unexpected**, across both themes; **26 axe scans / 0 confirmed violations**.
  Dialog hidden-background checks and some narrow-dialog contrast checks remain
  axe manual-review annotations, not a full accessibility pass. The actual
  `composer quality:browser` wrapper then exited **0**, with all 14 tests and
  both expected-failure probes passing. The existing local Chrome probe also
  retained **55 passing checks**.
- Safety/cleanup: **488 monitored source/configuration hashes** were unchanged
  during full CI validation; no production component, PHP business logic,
  migration, database configuration, or TestCase guard changes. Documentation
  evidence was updated afterward. Fixture listener/process counts were **0**;
  the exact unused legacy Chrome synthetic profile was verified and removed.
  Only ignored synthetic build/results files remain. No Laravel server or
  PostgreSQL connection was used by the browser harness.
- Additional validation: `composer validate --strict` passed; npm audit reported
  **0 vulnerabilities**; Semgrep **458 required files / 0 findings**; Gitleaks
  **491-file available-source snapshot / 0 findings**. Git metadata is absent,
  so neither a local tracked Git diff nor a fresh full-history scan is claimed.
  At this milestone the separate Composer advisory above still blocked
  dependency-security; EQ-09B1.1 below records its remediation.

### EQ-09B1.1 — Symfony security patch (2026-10-29)

- Incident: production-transitive Mime 8.1.7 affected by medium-severity
  **CVE-2026-45070 / PKSA-wtxr-p26d-nn42**, MIME parameter-name header injection.
  Patched and installed: **symfony/mime 8.1.10**, reference
  `8d1232052c9cdef170ed9608b3147bca4f61ff69`.
- Compatibility: Laravel 13.39.0 / PHP 8.4.2; Mime's PHP >=8.4.1 and existing
  Symfony dependency contracts are satisfied. Composer validation and locked
  platform checks pass. Root requirements, configuration and security thresholds
  remain unchanged.
- Scope: the user separately completed a general Composer update during
  inspection and authorized retaining it (including Mailer 8.1.10). The exact
  Mime-only `--minimal-changes --patch-only --no-scripts --no-plugins` dry-run
  and execution required **0 further package changes**. All 158 locked package
  records and lock metadata matched before/after the targeted command. This is
  not evidence that the user's broader update changed only Mime.
- Sources: preserved `composer.lock`, seven new isolated tests in
  `tests/Unit/MimeHeaderSecurityTest.php`, this ledger and ENGINEERING.md.
  No vendor source was edited manually, application feature code changed,
  isolation guard weakened, or database/schema configuration changed.
- Focused evidence: MIME tests **7 / 13 assertions**; isolated auth/notification
  selection **150 tests / 1,325 assertions** plus configuration-only isolation
  **26 / 74**. Tests cover CRLF/non-token rejection, unchanged state after rejected
  setters, valid quoted values and email serialization without transport, plus
  registration, verification, reset and invitation-notification regressions.
- Security: native locked Composer audit and full `composer security:check`
  both exited **0**; npm reported **0 vulnerabilities**. The EQ-09B1 blocking
  advisory is resolved without exclusions or reduced thresholds.
- Final validation: `composer ci:check` exited **0**, with **1,666 Pest tests /
  11,577 assertions**, global PHPStan level 8 + Strict Rules (**0 diagnostics**),
  Rector (**0 changes/errors**), Pint check, strict TypeScript, **36 Node tests**,
  frontend lint/format and production build. Focused architecture + MIME tests
  passed **13 / 310 assertions**; full CI also exercised architecture probes.
- Browser: `composer quality:browser` exited **0**, with **14 Playwright tests**
  across both themes plus successful intentional-violation and missing-tooling
  failure probes. Prior manual-review annotations and hosted/assistive-technology
  limitations remain as documented in EQ-09B1; this patch does not expand that
  accessibility claim.
- Check-only evidence: **489 monitored source/configuration hashes** were
  unchanged during full CI; documentation results were recorded afterward.
  Array mail delivery and SQLite in-memory isolation were used for application
  tests; no SMTP transport was used by the MIME regression. PostgreSQL guards,
  financial records, authentication configuration and application code were
  preserved. A hosted run against the retained user-updated lockfile is still
  required to verify the complete GitHub jobs on Linux.
- Education: temporary exact transitive constraints, partial-update solver scope,
  script/plugin isolation, concurrent lockfile changes, declared vs exercised
  PHP compatibility, exploit rejection vs valid behavior, and layered audit/
  regression/browser evidence. See ENGINEERING.md for the command and rationale.

### EQ-09B2A — Cross-browser accessibility (2026-10-29)

- Purpose: extend the existing synthetic React harness to Chromium, Firefox and
  WebKit, preserving database independence and existing quality/security gates.
  No package versions or lockfiles changed; Storybook remains deferred.
- Tools: Playwright **1.64.1**, axe integration/core **4.14.0**; bundled Chromium
  **156.0.8078.4 / 1248**, Firefox **157.0 / 1555**, WebKit **27.2 / 2370**.
  Windows engine builds are not native Safari or assistive-technology evidence.
- Sources: `playwright.config.ts`, `tests/Browser/shared-primitives.spec.ts`,
  `tests/Browser/focus.ts`, `tests/Browser/fixture/shared-primitives.tsx`, existing
  fixture entry/accessibility helpers/tests and probe configuration; shared
  `toggle`, `navigation-menu`, `tooltip`, `dialog` and `sheet` components;
  `.github/workflows/tests.yml` and `QualityCommandContractTest.php`.
- Coverage: 15 scenarios × six engine/theme projects. Added checkbox mixed/error/
  disabled behavior, radio-role toggle groups and dropdown choices, tooltips,
  collapsibles, sheets and navigation; retained existing password, transaction
  fields, dialog/select/menu checks. No absent Tabs/Popover/AlertDialog primitives
  were invented. Assertions cover actual keyboard/focus behavior and semantic
  descriptions, including background containment, 640/320-pixel reflow and
  reduced-motion/ordinary-motion states.
- Defects reproduced before correction: light focus indicators **1.963:1**
  (Toggle) and **1.134:1** (NavigationMenu trigger), 384-pixel tooltip in a
  320-pixel viewport, and active overlay motion despite reduced preference.
  Opaque existing tokens, restored link indicators, bounded tooltip wrapping and
  explicit reduced-motion state variants correct these. Explicit default link
  tab stops correct the observed WebKit native-Tab exit skipping the next link;
  caller overrides remain supported.
- Reviewed boundary: the user approved an **open navigation-content-only** axe
  scan after investigation of Radix 1.4.1's hidden focus-routing span reporting
  `aria-hidden-focus`. Whole-page scans before/after and bidirectional keyboard
  checks remain blocking. No rule/result suppression; the open whole-page state
  is not claimed clean. See ENGINEERING.md for exact scope and manual follow-up.
- CI: the existing required `browser-accessibility` job installs all three pinned
  engines and system libraries, uses one worker/zero retries, a 10-minute test
  deadline and 20-minute job deadline. The final eight-job `ci` gate retains its
  fail-closed behavior. The safe local `composer ci:check` contract is unchanged.
- Manual/hosted limits: NVDA on Windows, VoiceOver with native Safari on macOS,
  actual zoom, forced colors, pointer targets and authenticated financial pages
  remain unverified. ENGINEERING.md contains an actionable screen-reader checklist
  and requirements for future independently guarded financial E2E coverage.
- Final Windows browser evidence: **90 passed / 0 skipped / 0 flaky / 0
  unexpected**, **30 per engine**, covering both themes with zero retries. All
  confirmed axe violations within the reviewed scopes remain blocking; incomplete
  focus/contrast/navigation-attribute checks remain annotated for manual review.
  Focus rings measured **4.369:1** in light and **6.452–7.307:1** in dark.
  Both intentional unnamed-button and missing-browser probes confirmed **exit 1**;
  the probe runner exited **0** only after verifying the expected failures.
- Validation: `composer ci:check` exited **0**, including **1,666 Pest tests /
  11,577 assertions**, PHPStan 8 + Strict Rules, Rector dry-run, Pint, TypeScript,
  **36 Node tests**, frontend lint/format and production build. Separate guard
  tests passed **26 / 74 assertions**; focused workflow contracts **7 / 212**.
- Security: `composer security:check` exited **0**, no known locked Composer/npm
  vulnerabilities. Semgrep **1.182.1** scanned **462 required files / 0 blocking
  findings** with **6 passing probes**; Windows sandbox startup initially failed
  closed, then the unchanged command passed outside that restriction. Gitleaks
  **8.30.1** scanned a **495-file available-source snapshot / 0 findings**, with
  **4 passing probes**. Git metadata is absent, so a fresh full-history scan and
  tracked Git diff cannot be claimed locally; hosted full-history checks remain
  required.
- Safety: **492 monitored source/configuration hashes** were unchanged during
  validation, including protected configuration; evidence documentation was
  updated afterward. All four dependency manifest/lockfile hashes also match the
  start-of-task snapshot. Fixture server/test-runner and port-4178 listener counts
  were **0** after cleanup. No Laravel server or financial database was used by
  browser checks; application tests retained SQLite `:memory:` safeguards. No
  backend logic, migrations, database configuration or financial semantics changed.
- Education: engine-specific keyboard modality, Radix focus proxies versus actual
  keyboard routing, scoped automated evidence versus conformance claims,
  translucent focus contrast, available-width layout, reduced-motion selector
  specificity, deterministic rendering synchronization and manual AT evidence.

### EQ-09B2B — Authenticated financial E2E (2026-10-30)

- Purpose: real Laravel/Fortify/Inertia browser coverage through EQ-07's existing
  disposable PostgreSQL lease; no development database, test-only public routes,
  authentication bypass, financial-domain changes or dependency upgrades.
- New files: `playwright.e2e.config.ts`, `tests/E2E/financial.spec.ts`,
  `tests/FinancialE2E.php`, `tests/FinancialE2EServer.php`,
  `tests/FinancialE2EFixtures.php`, `tests/Support/FinancialE2EEnvironment.php`,
  `tests/Support/financial-e2e-router.php`,
  `tests/Unit/FinancialE2EEnvironmentTest.php`.
- Updated files: Composer/npm script manifests, `tsconfig.json`,
  `.github/workflows/tests.yml`, `QualityCommandContractTest.php`,
  `DependencySecurityContractTest.php`, `SecretSecurityContractTest.php`,
  ENGINEERING.md and this ledger.
- Tools: existing PHP 8.4.2 / Laravel 13.39.0 / Fortify 1.41.0, Playwright 1.64.1,
  axe 4.14.0 and PostgreSQL 18.6. The initial Docker service probe passed; after
  Docker's daemon became unavailable, actual journeys ran on a newly initialized
  private native PostgreSQL 18.6 cluster on loopback 65432. Development PostgreSQL
  was not substituted. Hosted CI retains EQ-07's digest-pinned container.
- Coverage: five Chromium journeys exercise real login/navigation/keyboard
  logout, separate guest sessions, household switching with member restrictions,
  nested cross-Team 404 and unauthorized-Team 403 without sentinel disclosure,
  exact recorded balance/date rendering and initial Inertia serialization,
  server-side transaction errors/help/keyboard associations, and real browser
  multipart CSV staging/retry with encrypted raw rows and no transaction commits.
  An unprotected request must return 419. Axe targets real forms/balance sections.
  CSV has no production upload interface yet: browser FormData calls the existing
  endpoint; no page or upload widget was invented.
- Safeguards: explicit additional opt-in, original endpoint/configuration and
  owner/OID/nonce/live-parent checks, capability checks before HTTP startup and
  financial requests, listener/bind refusal, no server reuse, per-lease response
  identity before credential entry, fresh run database and per-journey actors,
  one worker/zero retries, bounded processes/SQL statements, isolated private
  sessions/rate limits/views/uploads and no external browser/HTTP-client calls.
- Confirmed harness defects corrected: Windows permits a second bind probe on an
  occupied port, so startup also detects an active listener. Early Vite resolution
  preceded registration of its singleton and could consult the active developer
  hot file; an after-resolution callback plus a production-mode invariant fixes
  this without editing `public/hot`. Public asset requests bypass expensive
  framework/database bootstrap while financial requests retain lease checks.
- Interrupted-request cleanup: per-request private bootstrap files can survive
  child termination. A private per-run directory ledger allows parent cleanup
  through the unchanged EQ-07 path validator; regression tests reject unrelated
  paths and verify cleanup. Hard-killed owners can still leave orphans; no database
  adoption, force-drop, active-session termination or automatic recovery is added.
- Commands: `composer e2e:guard`, `composer test:e2e:probe`, `npm run test:e2e`
  (delegating to `composer test:e2e`). `composer ci:check` and the existing 90-case
  component suite remain independent. The new mandatory `financial-e2e` job is
  the ninth final-gate dependency, with existing pinning/read-only settings and
  executable failure/cancellation/skip/missing-result coverage.
- Final browser evidence: **5/5 real journeys passed**, zero retries, in **45.1s**;
  nonce, parent-session and invalid-credential server refusals plus controlled
  HTTP-child interruption passed separately. Each run reported verified cleanup.
  The unchanged component command passed **90/90** across three engines/two
  themes, plus both expected-failure probes.
- Guards/contracts: E2E configuration/filesystem tests **26 / 32 assertions**,
  existing SQLite guard **26 / 74**, existing PostgreSQL guard **55 / 96**.
  Focused guard/quality/security contracts passed **83 / 519** before the final
  compiled-view probe; focused workflow-security contracts then passed **3 / 313**.
  A Windows sandbox cleanup stall was resolved by stopping only that verified
  test process tree and rerunning identical tests with child-process permissions.
- Final `composer ci:check`: **exit 0**, **1,698 Pest tests / 11,694 assertions**,
  PHPStan 8 + Strict Rules, Rector, Pint, TypeScript, **36 Node tests**, frontend
  lint/format and production build. The first full run correctly rejected the
  new job under the old service allowlist. The revised security contract permits
  PostgreSQL only in the two named guarded jobs in `tests.yml`, requires the exact
  image/test credentials and forbids volumes; all other jobs remain service-free.
  No failing tests, rule severities or scanner thresholds were bypassed.
- Security: locked Composer/npm audits **exit 0**; Semgrep 1.182.1 **470 required
  files / 0 blocking findings**, **6 passing probes**; Gitleaks 8.30.1
  **503-file available-source snapshot / 0 findings**. Full Git history cannot
  be checked locally because Git metadata is absent.
- Safety/cleanup evidence: disposable PostgreSQL metadata showed **0 leased
  databases, 0 leased sessions and 0 public bootstrap tables**. The native test
  cluster was gracefully stopped and removed; two identified pre-ledger bootstrap
  remnants were safely removed. Final task-process, reserved-port listener and
  owned temporary-directory counts were **0**. **501 monitored source/configuration
  hashes** were unchanged during final checks, including `.env`, `public/hot` and
  the SQLite/PostgreSQL guards; evidence documentation was updated afterward.
  Both dependency lockfiles retain their original hashes. No development database
  was used, and full local CI passed after the test PostgreSQL service was stopped.
- Outstanding external verification: hosted Linux Actions/required-check settings,
  full Git-history scanning and manual NVDA/VoiceOver/native Safari review. Docker
  remained unavailable at final inspection, so removal of the earlier task-owned
  `intelledger-eq09b2b-postgres` container could not be verified. Once Docker is
  available, inspect that specific container (original ID starts `3a2ceb73fb51`)
  and clean up only that owned resource if it remains; do not remove unrelated
  containers or volumes. Native-cluster cleanup is independently verified above.
- Education: HTTP sessions versus array-backed unit-test sessions, real CSRF under
  cli-server, explicit tenant routes, Inertia initial-page serialization/version
  negotiation, framework singleton resolution timing, Windows bind/process-tree
  behavior, lease capabilities versus recovery/adoption, per-run filesystem
  ownership, scoped axe evidence and host-versus-local validation. See
  ENGINEERING.md for setup, safety boundaries and follow-up coverage.

### EQ-10A — AWS deployment architecture and operational design (2026-10-30)

- Purpose/status: implementation-ready **proposed** private synthetic-data preview
  design, not infrastructure creation or deployment authorization. The inline ADR
  in ENGINEERING.md records alternatives, cost assumptions, security boundaries,
  release/migration sequence, operational runbooks and the bounded EQ-10B backlog.
- Runtime evidence: locked Laravel **13.39.0**, Inertia Laravel **3.5.2**, Fortify
  **1.41.0**, PHP constraint **~8.4.1**, React 19, Tailwind 4, Vite Plus **1.3.0**,
  existing PostgreSQL **18.6** test baseline. Review covered Composer/npm config,
  Vite/Inertia, queue/cache/session/mail/database/filesystem/Fortify configuration,
  bootstrap health route, scheduled tasks, queued invitations and CSV staging/
  encryption Actions. No development configuration secrets or database contents
  were inspected. Existing nine-job CI and isolation contracts remain unchanged.
- Preferred architecture: 4 GB IPv4 Lightsail, Ubuntu 24.04 LTS, approved PHP 8.4
  package source, PostgreSQL 18, Nginx/FPM, one database queue worker/scheduler,
  separate 20 GB encrypted state disk and independent encrypted S3 backups.
  One host/AZ is explicitly **not HA**. A 2 GB alternative is capacity-constrained;
  EC2 with instance profile/IMDSv2/SSM is the preferred identity upgrade before
  financial data. No Redis, ALB, NAT, Kubernetes or managed database is required
  for the initial preview.
- Public prices checked 2026-10-30; estimated USD/month for us-east-1, 730h,
  no credits/tax/labor: **Lightsail 2 GB $26.58; 4 GB $38.58; EC2 2 GiB $35.20;
  4 GiB $50.53**. Includes bounded snapshots, S3 storage/requests, monitoring,
  DNS, secrets and a small restore-drill allowance; EC2 also includes EBS/IPv4/
  conservative egress. Managed PostgreSQL adds an **unverified $30–60 planning
  allowance**, requiring a saved calculator quote. Sources/formulas and excluded
  costs are in ENGINEERING.md. Suggested monthly alert budgets: $35/$50 for
  Lightsail, $65 for EC2 4 GiB. Alerts are not spending caps.
- Decisions/gaps: explicitly choose client rendering (SSR is currently enabled);
  `transaction_imports` stays local and encrypted initially, since neither the
  S3 adapter nor configurable disk mapping is present. `FILESYSTEM_DISK=s3`
  cannot move imports. APP_KEY recovery covers file frames/encrypted DB casts/
  TOTP; stable passkey handle secret must survive rotations. APP_KEY must never
  be regenerated during release. Raw PHP uploads require encrypted private temp
  storage. Attached-disk guarantees do not independently establish root-disk
  encryption. Ubuntu's default PHP 8.3 requires an approved PHP 8.4 source.
- Identity: no assumed Lightsail instance profiles; preview requires accepted,
  individually scoped/rotated host service keys, no app AWS key for current local
  storage, distinct app/migrator/backup identities and no runtime DDL privileges.
  EC2 avoids stored AWS keys, though shared-host role blast radius remains.
  Repository hardening/deployment credentials were explicitly deferred.
- Operations: immutable commit/run/digest releases built in CI, target-local
  protected cache generation, reviewed additive migrations, worker drain/restart,
  readiness beyond `/up`, sanitized low-cardinality metrics and 14-day logs.
  Target **24h RPO / 4–8 staffed-hour RTO** with 12-hour consistent encrypted
  backups, 7-day half-daily + four-week weekly retention and monthly restore
  proof. Automatic Lightsail snapshots are lost with source deletion unless
  retained as manual copies; independent S3 backup is essential. No HA, PITR,
  cross-account DR or regulatory compliance claim.
- Retention gap: import expiry metadata has no purge implementation; coordinated
  file/staging cleanup must preserve financial provenance. SSE-S3 does not replace
  application keys; future direct S3 writes must be tested against ACL-disabled
  bucket policies and DB/object-store compensation limitations.
- Validation: `npm run format:check` passed (177 files); independent integer-cent
  sums matched all four cost totals. SHA-256 comparisons found **615 monitored
  non-documentation files unchanged**, including lockfiles and protected
  environment/test-guard configuration. No new runtime tests are claimed; baseline remains
  **1,698 Pest tests / 11,694 assertions and 95 browser tests** from EQ-09B2B.
  No resources, dependencies, application code, migrations, database configuration
  or financial data were changed. No AWS CLI or database commands ran.
- Outstanding review: region/residency, stable domain/tester CIDRs, runtime package
  signatures, stored-key acceptance versus EC2, budget/operator/recovery custodians,
  actual Linux capacity/backup/TLS/IAM/migration rehearsal and future hosted
  deployment-environment protections. EQ-10B first prepares reviewable artifacts,
  host configuration and restore evidence; paid activation needs later approval.
- Education: bundled versus complete operating cost, single-host versus HA,
  control-plane IAM versus workload identity, application encryption versus
  storage encryption, secrets bootstrap/key escrow, immutable artifacts versus
  mutable runtime state, expand/contract migrations, RPO/RTO versus backup age,
  restoration versus code rollback, telemetry cardinality and cost-aware service
  selection. See ENGINEERING.md for the full decision and implementation gates.

### EQ-11A: Reusable engineering standards and Codex skills (2026-10-30)

- Purpose: convert verified EQ-01–10A practices into portable review standards and
  prepared teaching material, without new application behavior, tools or CI gates.
  [Standards index](engineering/README.md) separates mandatory automation,
  advisory conventions, agent-assisted procedures and manual production/security
  evidence across seven areas. Configurations remain the enforcement authority.
- Sources reviewed: ENGINEERING.md, this ledger, `.github/workflows/tests.yml`,
  Dependabot, Composer/npm manifests/locks/scripts, PHPStan/Rector/Pint, architecture/
  strict-type/quality/security tests, SQLite and PostgreSQL/E2E guards, Playwright,
  Vite/TypeScript, AGENTS.md, `boost.json` and local Boost MCP configuration.
  The early engineering summary now correctly names nine independent jobs and
  existing EQ-09 browser coverage; no workflow or command implementation changed.
- Reviewed locked tools: PHPStan **2.3.4**, Larastan **3.13.2**, Strict Rules
  **2.1.0**, Rector **2.8.1**, Pint **1.32.1**, Pest **5.3.4**, architecture plugin
  **5.0.0**, Boost **2.10.3**; React **19.3.1**, Vite Plus **1.3.0**, Playwright
  **1.64.1**, axe **4.14.0**. Semgrep **1.182.1** and Gitleaks **8.30.1** retain
  their existing pins. These are evidence snapshots, not new hardcoded tool gates.
- Five instruction-only entrypoints under `.agents/skills`: `engineering-php-review`,
  `engineering-domain-review`, `engineering-ci-security-review`,
  `engineering-accessibility-review`, `engineering-production-review`. Required
  name/description frontmatter and progressive references follow
  [official skill conventions](https://learn.chatgpt.com/docs/build-skills).
  Standard discovery/explicit invocation is intended; fresh-session routing is
  not claimed as tested. No optional UI metadata, executables, plugin or custom
  authentication/MCP abstraction was necessary.
- Version control: `.gitignore` admits only those **five SKILL.md files**, while
  Boost-managed skills, extra files in review-skill directories, personal agent
  files, AGENTS.md and Boost configuration stay ignored. An isolated temporary
  Git repository proved **five allowed and eight still-ignored cases**, then was
  removed. Actual workspace Git metadata remains absent; eligible files are not
  claimed as committed. Existing Boost skills, AGENTS and MCP settings are unchanged.
- [Adaptation guide](engineering/ADAPTING.md): inventory and map the target's own
  runtime, test safety, domain/tenant, commands, frontend and operations before
  porting. No Team schema, financial rules, PostgreSQL or AWS architecture is
  presumed. Java/Spring/React conceptual equivalents are sourced and explicitly
  uninstalled/unverified as integrations. Copy standards with skills and validate
  target references; instructions alone are not an enforcement mechanism.
- [Walkthrough syllabus](engineering/WALKTHROUGH.md): **13 sessions** connect each
  concept/tool/configuration to an isolated failure example, hands-on exercise,
  tradeoff, evidence limit and cross-stack equivalent. The educational walkthrough
  has not begun. Examples use existing synthetic probes and never persistent data.
- Validation: all **five installed skills** passed the bundled skill-creator
  validator. **117 local path/heading references** and **29 script aliases** were
  verified against actual files/manifests; two native Composer command references
  were distinguished from scripts. Installation hashes matched validated drafts.
  `composer test:isolation` passed **26 tests / 74 assertions**. Focused existing
  quality-command, source-security and secret-security contracts passed **13 tests /
  704 assertions**. `composer frontend:check` passed lint/format, including **185
  formatted files**. SHA-256 checks confirmed **654 monitored pre-existing source,
  configuration and Boost-skill files unchanged**, including both dependency
  lockfiles, `.env`, AGENTS/MCP config and SQLite/PostgreSQL/E2E guards.
- Safety: no executable source, existing CI gates, dependencies, migrations,
  database settings or data changed. No database-backed integration/browser
  service, AWS command, dependency installation or hosted-settings mutation ran.
  Full `composer ci:check` was not rerun for documentation/instructions/ignore
  patterns; previous full baseline remains **1,698 Pest / 11,694 assertions and
  95 browser tests** from EQ-09B2B, not a new result.
- EQ-11B: perform fresh-session bounded skill trials, calibrate findings on one
  non-financial target workflow and rehearse the prepared syllabus with sanitized
  evidence. Hosted CI/settings, complete Git history, manual NVDA/VoiceOver/native
  Safari, Storybook compatibility and Python tool-dependency monitoring remain
  open. AWS provisioning remains deferred until core MVP readiness and separate
  authorization. None of these open items is silently promoted to complete.
- Education: configuration versus policy versus skill, framework guidance ownership,
  observed versus claimed guarantees, negative probes, target-specific adaptation,
  authorization versus execution permissions, and evidence-based production claims.

### EQ-11B: Final independent validation and evidence reconciliation (2026-10-30)

- Deliverables: [final control/evidence matrix and risk register](engineering/VALIDATION.md),
  [skill invocation/portability assessment](engineering/PORTABILITY.md), clarified
  adaptation/reference contracts, and reconciled 13-session curriculum with exact
  command selections and Java/Spring/React equivalents. The full walkthrough has
  not begun; no application or CI implementation was changed.
- Independent skills: **five fresh-context Codex workers** read exact skill copies
  and raw source in an isolated 21-file, non-financial BookmarkBox snapshot, with
  no expected answers/prior conclusions. All confirmed initial catalog availability
  and explicit loading; reviews found principal-contract uncertainty, absent
  owner authorization, a permissive final CI gate, form accessibility defects and
  unsupported same-host HA/recovery claims. Recommendations and caveats matched
  the source; no installed-version/runtime/browser/Boost results were fabricated.
  All 21 file hashes were unchanged by review. Seven subsequent `php -l` checks
  passed. No ATLAS/other production repository was modified.
- Standalone CLI: installed **0.165.0**, ephemeral/read-only/no-user-config mode,
  no model override, 180s deadline. First trial timed out with no answer; remaining
  skills used actual fresh-context workers. This is **blocked CLI execution**,
  not proof of CLI or implicit copied-skill discovery. Five structural validators
  passed separately; structure alone was not used to claim behavioral testing.
- Portability correction: one adapted profile omitted a linked heading; it was
  corrected after the review and all **17 target skill references** resolved.
  Two workers self-corrected the wrong relative base. ADAPTING.md now specifies
  SKILL.md-relative resolution, stable/remapped anchors and separation of source
  evidence from target results. No skill entrypoint or application rewrite needed.
- Runtime inventory: PHP **8.4.2**, Node **24.21.0**, npm **12.2.1**; CI/manifest
  specifies npm **12.2.0**. Installed package versions are in VALIDATION.md.
  Local npm patch drift is explicit; no install/downgrade or compatibility claim
  for an unexecuted hosted runtime was made.
- Fresh full `composer ci:check`: **exit 0**, **1,698 Pest / 11,694 assertions**
  (Pest 232.911s), **36 Node tests**, PHPStan 8/selected Strict Rules **0 errors**,
  Rector **0 changes/errors**, Pint, strict TypeScript, lint/format and build.
  Configuration-only `composer test:isolation` passed **26 / 74** before the
  suite. The existing optional fontaine build warning remains informational.
- `composer security:check`: **exit 0**, no Composer vulnerability advisories;
  npm **0 findings** across all configured dependency types/severities. The
  npm report inventories 388 total dependencies; category counts overlap.
- SAST: sandboxed `composer source:check` first failed closed with exit **2** on
  executable version checking despite installed metadata. Unchanged native-
  permission rerun: **exit 0**, Semgrep **1.182.1**, **470 required files / 0
  blocking findings**, **6 probes**. An extra unconfigured version diagnostic
  stalled; Stop-Process failed in Windows, then native taskkill stopped only its
  identity-verified three-process tree. No rules, packages, thresholds, settings
  or unrelated processes were changed to obtain a pass.
- Secrets: `composer secret:check` returned **2 as required**, after Gitleaks
  **8.30.1** scanned **503 available-source files / 0 findings**, because this
  workspace has no Git history. Separate `composer secret:test`: **4 tests passed**.
  A temporary full public mirror (shallow=false) at main SHA
  `e0e48a307bacee64f02aa7d7f7d78536b5701e2c` passed the existing explicit history
  command: **41 commits / 0 findings**, exit 0. Remote history is not workspace
  provenance or the unpublished changes' history. No Git rewrite or bypass.
- `composer quality:browser`: **exit 0**, **90/90** component cases (**30 each**
  Chromium/Firefox/WebKit, light/dark projects) in **5.0m**; button-name-violation
  and missing-tooling probes both confirmed exit **1**. Synthetic harness only;
  no Laravel server/financial database or real data. Native Safari/AT remain open.
- PostgreSQL and authenticated HTTP tests: guards/contract regressions passed in
  the fresh Pest suite; real engine/journey results remain historical EQ-07B/
  EQ-09B2B evidence. No PostgreSQL migration, fixture, reset or live-data mutation
  ran in EQ-11B. Ordinary tests used verified SQLite in-memory data only.
- Hosted reconciliation: read-only API verified public visibility; main protection
  **404**, no rulesets; Dependabot alert endpoint **404 disabled** and security
  updates disabled; native secret scanning/push protection enabled. Observed tests
  runs **41031322356** and **40199628764** failed under an older single-job workflow.
  Remote-main workflow blob `86de1022fe3b6edd44cb382d48305609de6244c4` differs from
  local `f291e4dcc38b73b18b2519b6545bd80088d280d8`; current nine-job hosted success
  is **unverified**, not disproved by an old run or proved by unrelated successes.
  No GitHub settings/source mutations occurred.
- Docker cleanup reconciliation: read-only inspection found no container with the
  documented name `intelledger-eq09b2b-postgres` or original ID prefix
  `3a2ceb73fb51` on the current engine. This resolves that scoped uncertainty;
  no deletion performed and no claim about every volume/context.
- Final checks: **174 local links/anchors**, **37 IntelLedger script aliases**
  and the separate target's `check:types` alias validated; five skill-creator
  validators passed. Final formatting passed across **187 files**. The existing
  pinned Gitleaks wrapper explicitly scanned **12 toolkit/skill documents / 0
  findings**, compensating for their omission from metadata-free snapshot roots.
- Integrity: **571 monitored maintained source/configuration/skill files
  unchanged** by SHA-256, including dependency locks, `.env`, AGENTS/Boost/MCP,
  workflows and SQLite/PostgreSQL/E2E guards. The initial snapshot also contained
  **90 already-ignored generated Wayfinder files**; the required build regenerated
  **39** of those. The first comparison flagged them, then classified them against
  the existing generator/ignore contract; no new exclusion or gate was added and
  generated files are not falsely reported unchanged. Intended changes are seven
  engineering documentation files only.
- Cleanup: temporary synthetic fixture and public mirror removed after identity/
  content checks. Windows read-only Git pack files required a bounded retry that
  adjusted attributes only inside the owned mirror. No listeners remained on
  **4178/4182**. No persistent database, production data or unrelated repository
  was removed. Existing application/skill/configuration hashes remained unchanged.
- Recommendation: close **local implementation v1 with explicit deferrals**;
  do not claim all required commands green, current hosted enforcement, production
  readiness or manual WCAG certification. The final report bounds the later GitHub
  hardening phase. AWS provisioning/live backup restoration wait for core MVP;
  Storybook, manual AT, implicit CLI discovery and Python dependency monitoring
  remain separately tracked. Curriculum preparation is complete, delivery deferred.

### GH-01: Hosted CI verification — blocked preparation (2026-10-30)

- Status: **blocked, not completed**. The current workspace is still a source
  archive: Git cannot resolve its top-level, status/branch or remotes. A full
  checkout containing the EQ changes is required to establish the hardening
  branch's source/commit ancestry; its path was requested during preflight.
  No branch, commit, push, PR or hosted workflow was created by this task.
- Authenticated GitHub access verified read-only as `AnirudhVijayaraghavan`, with
  repository push/admin permissions and OAuth `repo`/`workflow` scope names
  (plus gist/read:org). No token value, secret or credential was disclosed or
  changed. Actual write execution was not tested.
- Remote repository/default branch verified:
  `AnirudhVijayaraghavan/aniara-intelledger`, `main`, HEAD
  `e0e48a307bacee64f02aa7d7f7d78536b5701e2c`. Remote workflow blob
  `86de1022fe3b6edd44cb382d48305609de6244c4` has the older single `ci` job and
  `composer setup`. Local nine-job workflow blob is
  `f291e4dcc38b73b18b2519b6545bd80088d280d8`, SHA-256
  `0b5c0ce3bbc14e07dd28e25e6e7109a5a254afd2ae2687854e2d2a0fb140e8d2`.
- Recent observed failures remain old runs: PR **41031322356** at
  `2e0f8d69b2672c886339732e93bd0378272df9b4`, push **40199628764** at main HEAD,
  and push **40021180284** at `b4910c7e7a411400d65074b15fc6551919b35a59`.
  Their URLs and distinction from the current implementation are retained in
  [VALIDATION.md](engineering/VALIDATION.md#hosted-observations-and-bounded-github-hardening-phase).
- YAML preflight confirmed the nine named jobs/final gate dependencies, Ubuntu
  runner declarations, PHP 8.4/Node 24/npm 12.2.0, read-only permissions and
  disposable PostgreSQL/E2E services without persistent volumes or repository-
  secret expressions. This is source inspection only. **All nine current hosted
  jobs and final ci are not run/unverified**; Linux behavior, actual memory,
  scanner/browser installation, full-history coverage and runtime cleanup remain
  pending. No unsupported hosted pass or portability fix is claimed.
- [Exact resumption steps](engineering/VALIDATION.md#exact-resumption-steps) require
  a full EQ checkout or reviewed archive reconciliation into a separate full clone,
  `hardening/gh-01-hosted-ci`, a PR to main and actual run/SHA/job evidence. A new
  clone alone contains old source; do not publish a workflow-only or unreviewed
  snapshot or invent `.git` history. No direct main push is authorized/performed.
- Documentation-only validation applies to these two evidence files. Application,
  workflow, manifests/locks, isolation guards, development configuration/data,
  GitHub settings and AWS resources remain unchanged. GH-02 still requires a
  verified current-source hosted pass and separately approved protection/settings
  work. The educational walkthrough has not begun.
- Preflight validation: `npm run format:check` **exit 0 / 187 files**, **47 local
  references/anchors** valid across the two updated evidence documents, and
  **13 protected workflow/manifest/lock/guard/configuration hashes unchanged**.
  No local test rerun was substituted for the missing hosted execution.

### GH-01A: Cold-checkout safety fixtures and cleanup evidence (2026-10-30)

- PR #2 now contains the reviewed implementation at initial head
  `c78737271a4ec8bc95b0735225915e6144e99ee0`. Run **41136929148** exposed three
  safety-test failures across the SQLite, PostgreSQL and financial E2E jobs;
  final `ci` correctly failed. The earlier blocked GH-01 preparation remains
  historical evidence, not the current PR state.
- Reproduced all three failures on a fresh isolated Linux filesystem: missing
  `.phpunit.cache` parents prevented fixture creation. E2E canonical-path refusal
  and PostgreSQL's missing-directory no-op were correct. Fixtures now assert
  recursive setup and restore owned artifacts with `finally`; the E2E runner also
  checks cold-start directory creation. Invocation/environment/lease guards and
  the workflow gate are unchanged.
- Additional regression evidence found cache cleanup could delete allowed evidence
  before encountering an invalid entry. A full inventory preflight now rejects
  unexpected files/directories and symlinks before deletion. New cases verify
  successful cleanup and retention; isolated Linux symlink probes also pass.
- Focused Windows and cold Linux checks: **85 tests / 161 assertions**;
  configuration-only SQLite checks: **26 tests / 74 assertions**. Full validation
  and hosted follow-up are recorded in
  [VALIDATION.md](engineering/VALIDATION.md#gh-01a-hosted-safety-test-remediation-2026-10-30).
  No production/development database, GitHub settings or AWS resources are changed.
- Complete serial `composer ci:check`: **1,702 tests / 11,727 assertions**, exit 0.
  Dependency audits and Semgrep scan/six failure probes pass. Disposable PostgreSQL
  **18 checks / 114 assertions**, interrupted-worker probes and **five** real E2E
  journeys pass, with verified cleanup. Hosted post-fix results remain a separate
  acceptance step; a local Debian container is not a hosted Ubuntu pass.
- Published fixture fix `3c17eae` and inspected Ubuntu run **41141154435**:
  original safety failures resolved; seven validation jobs passed. Newly reached
  HTTP interruption exposed POSIX supervisor termination leaving a child listener.
  A bounded synthetic regression reproduces it; explicit signal unwinding now
  reaches owned-child cleanup. Only the two affected test jobs request `pcntl`;
  missing POSIX support refuses execution. Fresh Linux safety: **86/164**;
  Windows E2E safety: **28/43**. The `ci` gate is unchanged.
- That run also reported one WebKit/dark reflow **color-contrast** finding
  (**89/90 component tests passed**), separately tracked without suppression or
  unrelated UI changes. Final local/hosted follow-up is recorded in VALIDATION;
  do not conflate the original three fixed assertions with these later failures.
