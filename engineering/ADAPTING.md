# Adapting the engineering standards

This is a migration procedure, not a promise that copying IntelLedger's files
will make another application production-ready. Start with the
[standards index](README.md). Keep the source application's evidence separate
from the target application's validation.

## Another Laravel application

1. **Inventory without initialization.** Inspect the target's manifests/lockfiles,
   installed PHP/framework/frontend versions, routes, AGENTS/Boost guidance, scripts
   and CI. Identify existing formatting, static analysis, test and security tools.
   Do not execute `setup`, provider boot code, migrations or live database queries
   merely to collect the inventory. Reuse installed tools before proposing packages.
2. **Map boundaries.** Record actual controller/validation/domain/persistence paths,
   authenticated principal, authorization model and tenant boundary, if any. A
   single-user app may have no household. Do not create Team/workspace tables,
   copy financial enums or impose IntelLedger's account/audit schema.
3. **Establish safety first.** Before database-backed tests, inspect resolved config,
   inherited URLs, read/write connections and cached config. Implement/prove the
   target's fail-closed guard in a separately authorized task. Do not copy a SQLite
   configuration over an existing test strategy without understanding it. Use
   isolated refusal probes before any refresh/migration; persistent databases are
   never fallback test targets.
4. **Preserve the command contract, adapt implementations.** Separate check-only
   backend/frontend commands, isolated tests and installation/initialization.
   Identify generated output and build-time framework boot requirements. Name the
   target's aliases explicitly; IntelLedger command names are examples, not an
   assumed interface. Bound memory/workers using measured target constraints.
5. **Raise one boundary at a time.** Inventory diagnostics, fix genuine issues,
   add meaningful regressions and promote a level/rule only after the chosen
   scope passes. Keep scoped strict declarations and reviewed Rector rules; do
   not import a broad baseline, blanket ignores or every modernization recipe.
6. **Rehearse real-engine invariants separately.** Match the target's production
   database when locking/precision/DDL matters. A different application cannot use
   IntelLedger's lease names/credentials unchanged: adapt ownership proof, allowed
   endpoints, lifecycle and interruption tests to its own disposable service.
   Containers alone do not prove a client selected the correct database.
7. **Retain frontend and framework conventions.** Use the target's existing
   components, tokens, routing generators and build tools. Do not add Vite Plus,
   Wayfinder, Radix, a chart library or IntelLedger's branding just to conform.
   Preserve equivalent strict TypeScript, check-only lint/format, keyboard/error
   contracts and synthetic browser isolation. Inspect plugin/peer compatibility
   before proposing Storybook or other dependencies.
8. **Make security and CI failure observable.** Inventory advisory/SAST/secret
   scanning and current-tree versus history scope. Add representative isolated
   failure/error probes in a separate implementation task. Adapt trusted triggers,
   least privilege, pinned tools and a fail-closed final gate. Keep hosted settings
   verification distinct from committed workflow correctness.
9. **Design the target's operations.** Choose runtime, mail, storage, identities,
   secrets, release artifact, migration and recovery strategy from actual needs.
   Do not inherit Lightsail, AWS, PostgreSQL, storage encryption keys, RPO/RTO or
   pricing assumptions. Check local upload/temp/log and backup paths too.
10. **Record target evidence and review.** Document actual commands/results, safety
    refusals, limits, owners and hosted/manual gaps. Only then describe that scope
    as enforced. Keep new controls incremental and compatible with target CI.

Before adopting, fill a small target profile in the target's own documentation:

| Decision                 | Required target evidence                                                           |
| ------------------------ | ---------------------------------------------------------------------------------- |
| Runtime and dependencies | Installed versions, lockfiles, platform constraints and supported CI runtime.      |
| Domain / tenancy         | Actual ownership, authorization and data-isolation boundary, or explicit absence.  |
| Test safety              | Effective-configuration refusal evidence before database mutation.                 |
| Commands / CI            | Local aliases, generated outputs, resource bounds, failure probes and hosted gaps. |
| Sensitive data           | Serialization/logging/storage/key/retention contracts relevant to that product.    |
| Browser accessibility    | Component and real-app scope, engine availability and manual AT gaps.              |
| Operations               | Reviewed release/rollback/restore plan, measured recovery and named ownership.     |

### Porting the skills

Copy `engineering/` and the five `engineering-*-review` skill folders to the same
relative repository locations. Replace source links/profile details with the
target's real paths; a broken reference is a failed adaptation, not permission to
invent missing code. Keep only useful skills, avoid duplicate names in user and
repository scopes, and review routing descriptions. If a target has no finance
domain, rename/narrow domain review to its own invariants or omit it.

Resolve a skill link from **the directory containing SKILL.md**, not the shell's
working directory or `.agents/`. For example,
`.agents/skills/<name>/../../../engineering/README.md` reaches the repository's
`engineering/README.md`. Preserve each linked heading when shortening a target
profile, or deliberately update the skill's anchor references with it. Check
files **and heading anchors** before the independent trial. EQ-11B's bookmark
trial caught a missing `mandatory-command-and-ci-contract` heading after a profile
was shortened; [PORTABILITY.md](PORTABILITY.md) records the correction.

Treat IntelLedger's [validation report](VALIDATION.md), milestone counts and
operational decisions as source evidence only. Omit them from a target's active
claims or clearly label them historical; write fresh target evidence instead.

Keep Boost's maintained skills and MCP configuration under the target's normal
installation/ownership process. These review skills do not replace framework
documentation. Preserve a narrow version-control allowlist if generated agent
files are ignored; do not copy personal `.codex` settings. Validate SKILL.md
frontmatter, relative links, script aliases, discovery in a fresh session and a
read-only trial review before describing adoption as complete.

## Conceptual equivalents across stacks

These are candidates to evaluate in a separate target project, **not installed
tools or verified Java integration**. Choose versions compatible with its JDK,
Spring Boot and build system; equivalents protect related properties, not
identical language/runtime behavior.

| IntelLedger concept                      | Java / Spring Boot equivalent                                                                                                                                   | React / TypeScript transfer                                                                     |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Composer runtime/locked graph            | JDK toolchain + Maven/Gradle wrapper, dependency management and verification/locking where supported; a BOM alone is not a lockfile.                            | Node/package-manager version + lockfile + reproducible install.                                 |
| PHPStan/Larastan + selected strict rules | Java compiler plus selected nullness/static analyzers, such as NullAway/Error Prone or SpotBugs, after compatibility review. Java references can still be null. | Strict TypeScript and runtime validation of untrusted JSON; types alone do not validate input.  |
| Scoped strict scalar contracts           | Explicit API types, null contracts and runtime input validation; no direct PHP caller-coercion equivalent.                                                      | Avoid unsafe assertions and validate unknown at boundaries.                                     |
| Pint check                               | Existing Java formatting/style check, e.g. Spotless/Checkstyle; select check mode rather than apply/fix.                                                        | Existing Oxlint/Oxfmt here; preserve the target's working tooling.                              |
| Reviewed Rector dry-run                  | Allowlisted OpenRewrite recipes, dry-run and reviewed diff before applying.                                                                                     | Reviewed codemod with a bounded file scope; no bulk rewrites by default.                        |
| Pest architecture boundaries             | ArchUnit package/layer/dependency tests over compiled classes.                                                                                                  | Module import boundaries with the target's existing dependency tooling, plus review.            |
| Pest feature/security tests              | JUnit with Spring test/HTTP facilities and explicit authenticated/unauthorized cases.                                                                           | Component interaction tests and independent real HTTP E2E.                                      |
| Request-bound User + policies            | Validate the actual request/security-context principal and enforce resource/tenant authorization; Spring request and method security are complementary.         | Frontend state is presentation, never an authorization boundary.                                |
| Brick Math + decimal strings             | BigDecimal from decimal strings with explicit scale/rounding; avoid constructing financial values from double.                                                  | Preserve decimal strings; display-only chart coordinates may be numeric.                        |
| Transaction locks/revisions/idempotency  | Transactional services, appropriate isolation/locking, version tokens and database uniqueness; ORM annotation alone is insufficient proof.                      | Handle conflicts explicitly; never calculate a replacement financial result client-side.        |
| Disposable PG leases                     | Testcontainers or an equivalent isolated service plus verified datasource configuration, ownership and cleanup.                                                 | Browser server/context identities and refusal to reuse unrelated services.                      |
| Check-only aggregate + required jobs     | Maven/Gradle/CI stages that propagate every required result and distinguish install/build/test/deploy.                                                          | Same locked build, utility tests, browser checks and non-mutating formatting contract.          |
| Semgrep/Gitleaks/dependency auditing     | Reuse compatible local scanners; evaluate JVM dependency audit/SBOM support separately.                                                                         | Include production and build-tool dependencies; current-tree scan does not prove history clean. |
| Operations evidence                      | Actuator-style health/readiness, worker/scheduler checks and tested backups as appropriate; secure management endpoints.                                        | Synthetic assets/fixtures and measurable accessibility; no framework implies WCAG compliance.   |

Primary references for these conceptual mappings:
[ArchUnit dependency tests](https://www.archunit.org/userguide/html/000_Index.html),
[OpenRewrite recipes](https://docs.openrewrite.org/running-recipes/getting-started),
[Spring method authorization](https://docs.spring.io/spring-security/reference/servlet/authorization/method-security.html),
[BigDecimal semantics](https://docs.oracle.com/en/java/javase/17/docs/api/java.base/java/math/BigDecimal.html),
[Testcontainers](https://java.testcontainers.org/).
No Java commands, dependencies or sample application are added by EQ-11A.
