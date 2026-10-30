# Final engineering walkthrough: source material and syllabus

**Preparation only.** The walkthrough has not started. This curriculum uses
[current standards](README.md), [adaptation guidance](ADAPTING.md), dated
[milestones](../ENGINEERING_MILESTONES.md) and working source as its evidence.
Recheck versions/results at delivery time; do not narrate old counts as fresh runs.

## Learning contract

Trace a requirement through **contract → guard → intentional failure → successful
check → CI result → remaining uncertainty**. Each session should identify the
tool's actual scope, configuration owner, tradeoff and a failure it cannot detect.
Hands-on exercises below are proposals for isolated synthetic workspaces, never
changes to development financial data or demonstrations against a persistent DB.

## Sessions and evidence

| Session / concepts                                      | Working source                                                                                                                                                                                                                      | Hands-on example and failure scenario                                                                                                                                                | Tradeoff / cross-stack transfer                                                                                                                   |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Evidence and reproducible command contracts          | [Composer](../composer.json), [quality helper](../tests/Support/QualityEnvironment.php), [command tests](../tests/Unit/QualityCommandContractTest.php); EQ-01/02.                                                                   | Follow `ci:check` into each alias; show a check failure stops the aggregate while generated caches remain allowed. Distinguish setup from checks.                                    | Faster local baseline versus complete independent CI coverage; Maven/Gradle phase equivalents.                                                    |
| 2. Runtime types, nullability and strict callers        | [PHPStan](../phpstan.neon), [strict contracts](../tests/Unit/StrictTypeContractsTest.php); EQ-03.                                                                                                                                   | Explain a real nullable principal fix and why PHPDoc or a cast cannot prove it. Demonstrate strict caller rejection in a synthetic DTO fixture.                                      | Scoped migration versus unsafe bulk declarations; Java null analysis and TypeScript runtime boundaries.                                           |
| 3. Refactoring tools and dependency direction           | [Rector](../rector.php), [architecture rules](../tests/Support/ArchitectureRules.php), [probes](../tests/Unit/ArchitectureRulesTest.php); EQ-04/05.                                                                                 | Run an isolated forbidden DTO dependency/float probe and review an allowlisted Rector dry-run. Leave no invalid source behind.                                                       | AST syntax/dependency checks versus complete semantic proof; ArchUnit and OpenRewrite.                                                            |
| 4. Database incident and configuration precedence       | [TestCase](../tests/TestCase.php), [configuration-only tests](../tests/Unit/TestCaseTest.php), [phpunit.xml](../phpunit.xml); EQ-01/07.                                                                                             | Use the existing refusal probes for cached config, inherited URLs and wrong drivers before any refresh. Explain the development-database incident.                                   | Environment labels versus resolved target; equivalent fail-closed datasource preflight in any stack.                                              |
| 5. Real PostgreSQL integrity and contention             | [runner](../tests/PostgreSqlIntegration.php), [lease](../tests/Support/DisposablePostgreSql.php), [workers](../tests/Support/PostgreSqlWorker.php); EQ-07A/B.                                                                       | On an independently verified disposable service only, observe lock barriers, one winning duplicate reversal and uniqueness arbitration; terminate an owned child and prove rollback. | SQLite speed versus engine-specific evidence; isolation/lock/version-token distinctions and Testcontainers.                                       |
| 6. Authentication input and explicit principals         | [resolver](../app/Http/AuthenticatedUserResolver.php), [resolver tests](../tests/Unit/AuthenticatedUserResolverTest.php), [Fortify regressions](../tests/Feature/Auth/AuthenticationInputBoundaryTest.php); EQ-03B/06A.             | Contrast array-valued email HTTP 500 with validated JSON 422/browser redirect. Trace request/global-user disagreement and independent Team authorization.                            | Authentication versus authorization, normalization order and abuse limits; Spring principal/method security.                                      |
| 7. Financial precision, history and read-only contracts | [CreateTransaction](../app/Actions/Finance/CreateTransaction.php), [activity calculator](../app/Actions/Finance/FinancialActivityCalculator.php), [calculator tests](../tests/Feature/Finance/FinancialActivityCalculatorTest.php). | Follow liability expense signs, a reversal across posting periods and exact decimal JSON. Show why transfers are excluded and missing observations are not zero.                     | Recorded activity versus verified cash flow; BigDecimal/decimal strings, with domain rules adapted rather than copied.                            |
| 8. React quality and component accessibility            | [Vite policy](../vite.config.ts), [tokens](../resources/css/app.css), [utility tests](../resources/js/lib/accessibility.test.mjs); EQ-02/09A.                                                                                       | Inspect keyboard-operable password toggle, combined help/error associations and light/dark contrast assertions. Introduce an isolated violation and observe failure.                 | Owned primitives versus generated code, semantic tokens versus branding; TypeScript/Hooks checks do not prove accessibility.                      |
| 9. Component browsers versus authenticated E2E          | [component config](../playwright.config.ts), [E2E config](../playwright.e2e.config.ts), [E2E guard](../tests/Support/FinancialE2EEnvironment.php); EQ-09B.                                                                          | Compare synthetic three-engine axe checks with real CSRF/session/Inertia flows. Demonstrate occupied-port/capability refusal without reusing another server.                         | One worker/reproducibility versus throughput; axe scope, reduced motion, reflow and manual AT limitations.                                        |
| 10. Dependency, source and secret security              | [audit aliases](../composer.json), [Semgrep rules](../semgrep.yml), [Gitleaks metadata](../gitleaks.lock.json), [security probes](../tests/Unit/SecretSecurityContractTest.php); EQ-06.                                             | Trace the MIME advisory/minimal patch incident; use synthetic scanner probes and simulated advisory outage. Explain tree versus history and sanitized findings.                      | Tool pinning versus monitoring, transitive/dev exposure, heuristics versus exploitable defects; no real credential demonstrations.                |
| 11. CI trust and failure propagation                    | [workflow](../.github/workflows/tests.yml), [gate probes](../tests/Unit/DependencySecurityContractTest.php), [Dependabot](../.github/dependabot.yml); EQ-01/06/09.                                                                  | Follow all nine required results; demonstrate failure, cancellation, skipped and missing-result rejection using existing probes.                                                     | Pinned Actions/least privilege versus runtime trust; committed gate versus actual branch protection/hosted verification.                          |
| 12. Operations, costs and readiness decisions           | [proposed AWS ADR](../ENGINEERING.md#eq-10a--aws-preview-architecture-and-operational-design); EQ-10A.                                                                                                                              | Paper/tabletop exercise: failed release, unavailable APP_KEY, expired CSV retention and host loss. Calculate the documented budget and choose code rollback versus restore.          | RPO/RTO versus snapshots, one server versus HA, workload identity versus stored keys. No provisioning or restore against persistent environments. |
| 13. Portable standards and agent reviews                | [adaptation guide](ADAPTING.md), [review contract](README.md#agent-assisted-review-contract); EQ-11A.                                                                                                                               | Map a small non-financial Laravel workflow to these standards; use one skill for a bounded review and assess its evidence. Sketch Java/React equivalents without installing tools.   | Reusable process versus domain schema/hosting assumptions; skill structure validation versus actual behavioral reliability.                       |

## Command and cross-stack handover map

The table above supplies concepts, source files, exercises, failures and tradeoffs.
This map adds exact existing entrypoints and the equivalent boundary to explain.
Fresh execution scope/results are in [VALIDATION.md](VALIDATION.md); the narrow
Pest commands below are future teaching selections, not claims of separately
rerunning every file during EQ-11B. Their tests were included in the full suite.
Run `composer test:isolation` first; a narrow selection uses
`php -d memory_limit=512M vendor/bin/pest <listed test file> --compact` from the
repository root and retains the existing test guard. Replace the listed file
placeholder before execution; never substitute persistent database settings.

| Session | Commands / selected evidence                                                                                                                    | Java/Spring Boot and React transfer                                                                                 |
| ------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| 1       | `composer ci:check`; select `tests/Unit/QualityCommandContractTest.php`.                                                                        | Maven/Gradle stages and frontend command graphs; installation versus checking.                                      |
| 2       | `composer types:check`; select `tests/Unit/StrictTypeContractsTest.php`.                                                                        | Compiler/nullness contracts and TypeScript unknown-input validation; no direct PHP coercion equivalent.             |
| 3       | `composer rector:check`; select `tests/Unit/ArchitectureRulesTest.php`.                                                                         | Reviewed OpenRewrite and ArchUnit; module dependency direction in React.                                            |
| 4       | `composer test:isolation`; read `tests/Unit/TestCaseTest.php`.                                                                                  | Effective Spring datasource/profile resolution and frontend harness/server identity.                                |
| 5       | `composer postgres:guard`; explicitly opted-in `composer test:postgres` only in an authorized disposable environment.                           | JPA/database locking, version tokens and Testcontainers; client conflict feedback.                                  |
| 6       | Select `tests/Unit/AuthenticatedUserResolverTest.php` and `tests/Feature/Auth/AuthenticationInputBoundaryTest.php`.                             | Request principal versus Spring authorization and frontend presentation state.                                      |
| 7       | Select `tests/Feature/Finance/FinancialActivityCalculatorTest.php`; `composer test:isolated` for related regression coverage.                   | BigDecimal/string JSON and backend-owned monetary contracts; no JS financial recomputation.                         |
| 8       | `composer frontend:types`, `composer frontend:check`, `composer frontend:test`.                                                                 | The same React/TypeScript semantic, token and keyboard boundaries with either backend.                              |
| 9       | `composer quality:browser`, `composer e2e:guard`; opted-in `npm run test:e2e` only with its verified lease.                                     | Component versus Spring/HTTP E2E, CSRF/sessions, process ownership and manual AT.                                   |
| 10      | `composer security:check`, `composer source:check`, `composer secret:check`; source/secret probe aliases separately if an earlier phase blocks. | JVM SCA/SAST/secret equivalents need target integration; React build dependencies also matter.                      |
| 11      | Select `tests/Unit/DependencySecurityContractTest.php` and `tests/Unit/SecretSecurityContractTest.php`; read actual hosted results separately.  | Stack-independent CI trust, required checks and fail-closed aggregation.                                            |
| 12      | Review EQ-10A ADR; tabletop only, no deploy command or live restore.                                                                            | Spring readiness/worker lifecycle and SPA artifact delivery; RPO/RTO and key custody are stack-independent.         |
| 13      | Explicit `$engineering-*-review` invocation; host-supplied skill-creator validator and target reference checks.                                 | Target profile mapping; candidate Java tooling and React infrastructure are evaluated, not automatically installed. |

## Delivery prerequisites and exercises

Before a practical session, identify the exact commit/artifact, installed tools,
expected command exit codes, data/port/process ownership and cleanup plan. Use
existing failure probes rather than silently weakening a gate for a demonstration.
Do not print live secrets, auth payloads, financial statements or raw scanner
snippets. A prerequisite failure is a lesson in safe refusal, not justification
for substituting the development database or downloading unreviewed tools.

For each hands-on example preserve: the initial contract, synthetic input,
expected refusal/failure, actual result, the accepted correction where authorized,
successful check and remaining gap. Keep screenshots/traces synthetic and scrubbed.
Performance/memory limits, platform differences and scanner network outages belong
in the explanation rather than being hidden as flaky tests.

Assessment: a learner should be able to diagnose one failing boundary, select the
smallest safe test, distinguish a tool finding from a confirmed vulnerability,
explain the authorization/ownership proof, and describe the equivalent boundary
in another stack. Passing every tool is not a substitute for these explanations.

## Reconciled evidence and remaining delivery work

- [Five fresh-context reviews](PORTABILITY.md) executed on a synthetic non-financial
  Laravel workflow. Standalone CLI timed out; implicit/copied-skill discovery and
  broader task families remain unverified.
- [Hosted inspection](VALIDATION.md#hosted-observations-and-bounded-github-hardening-phase)
  found an older failing workflow, unprotected main and disabled Dependabot alerts/
  security updates. Native secret scanning/push protection are enabled. Current
  nine-job hosted execution and approved settings changes remain open.
- A 41-commit explicit public mirror history scan passed; the metadata-free
  workspace's secret aggregate still refuses to claim complete local history.
- Manual NVDA/VoiceOver, native Safari, actual zoom and forced-colors review;
  Storybook compatibility remains unresolved, with no installation attempted here.
- Semgrep Python dependency monitoring and broader engine crash/deadlock/recovery
  scenarios beyond the bounded existing tests.
- Production runtime/storage/mail/retention and recovery implementation. AWS
  provisioning stays deferred until core MVP readiness and explicit authorization.

The syllabus is prepared and reconciled; it has not been delivered or timed as a
full course. Before delivery, refresh exact versions/results, select sanitized
evidence and rehearse the exercises with their stated safety prerequisites.
Starting the full walkthrough, changing hosted settings or implementing deployment
remains outside this milestone. The Docker container-name/original-ID absence
check is resolved for the inspected engine; it is not proof about every volume
or other context. See the [risk register](VALIDATION.md#risk-and-deferral-register).
