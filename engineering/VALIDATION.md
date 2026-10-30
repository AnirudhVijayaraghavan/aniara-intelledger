# Enterprise Engineering Excellence v1: final evidence

**Assessment: EQ-11B, 2026-10-30.** This report reconciles working configuration,
fresh executions, historical evidence and explicit deferrals. It does not certify
production security, accessibility or deployment. Start with the
[standards](README.md), [skill/portability trials](PORTABILITY.md) and
[curriculum](WALKTHROUGH.md).

## Status vocabulary and source identity

- **L — locally verified:** the corresponding check actually ran successfully
  during EQ-11B, with scope and negative evidence below.
- **H — hosted observation:** a read-only GitHub API response or named hosted run
  was inspected. An enabled setting is not an end-to-end operational probe.
- **M — manually verified:** actual human/assistive-technology/operational evidence.
  No new AT, live restore or production manual verification was performed here.
- **U — unverified:** no corresponding execution/evidence for the claim.
- **B — blocked:** an attempted command failed/refused/timed out; no clean result.
- **D — deferred:** a deliberate later milestone or operational decision.

Prior local results are labelled **historical**, with their milestone; they are
not fresh EQ-11B passes. Source inspection can establish a configuration or a
visible defect but is not substituted for executing its runtime check.

The working archive has no Git metadata. The public remote's inspected main
commit is `e0e48a307bacee64f02aa7d7f7d78536b5701e2c`; its workflow differs from the
local nine-job configuration. Local workflow blob ID:
`f291e4dcc38b73b18b2519b6545bd80088d280d8`; remote-main blob:
`86de1022fe3b6edd44cb382d48305609de6244c4`. A local pass cannot be attributed to
that remote commit, or vice versa. Final source-integrity checks cover the
pre-existing application/configuration/skill files; intentional documentation
changes are checked separately after editing.

Installed local runtime: **PHP 8.4.2, Node 24.21.0, npm 12.2.1**. Composer requires
`~8.4.1`; CI specifies PHP 8.4, Node 24 and npm **12.2.0**. The npm patch difference
is recorded, not hidden or resolved through an unrequested installation. Actual
hosted runtime parity and current production PHP patching remain unverified.

## Control and evidence matrix

Versions were read from installed Composer metadata and Node package manifests;
scanner/CLI versions were checked through their existing wrappers or CLI. Each
portable control still needs target-specific paths, tools and probes.

| Control / purpose                           | Installed tool and source configuration                                                                                                                                                                                 | Exact command / configured CI job                                                    | Positive and negative evidence                                                                                                                                                                             | Transfer / status and limitations                                                                                                                                                                |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Reproducible local quality contract         | Composer scripts; [QualityEnvironment](../tests/Support/QualityEnvironment.php), [command tests](../tests/Unit/QualityCommandContractTest.php)                                                                          | `composer ci:check`; `backend-quality`, `frontend-quality`, `isolated-tests`         | **Exit 0**; backend/frontend checks and **1,698 Pest / 11,694 assertions**, **36 Node tests**. Existing contract tests reject setup/migration/write-mode paths and unsafe environments.                    | **L**. Aggregate excludes independent security/PG/browser/E2E/deployment checks. Caches/assets may be generated; maintained source must remain unchanged.                                        |
| PHP type/nullability safety                 | PHPStan **2.3.4**, Larastan **3.13.2**, Strict Rules **2.1.0**; [phpstan.neon](../phpstan.neon)                                                                                                                         | `composer types:check`; `backend-quality`                                            | Full configured analysis passed with **0 diagnostics** through CI. Actual strict-rule unsafe-call/loose-comparison probes in command contracts passed in Pest.                                             | **L**. Level 8 and two selected rules, not all Strict Rules; static analysis does not validate arbitrary mixed runtime input.                                                                    |
| Formatting and bounded modernization        | Pint **1.32.1**, Rector **2.8.1**; [pint.json](../pint.json), [rector.php](../rector.php)                                                                                                                               | `composer lint:check`, `composer rector:check`; `backend-quality`                    | Check-only Pint passed; Rector reported **0 changed files / 0 errors**. Existing isolated transformation/failure and non-rewrite contracts passed in Pest.                                                 | **L**. Rector covers Data/Enums with an allowlist; no wholesale API/dead-code transformations.                                                                                                   |
| Architecture and strict declarations        | Pest **5.3.4**, architecture plugin **5.0.0**; [architecture](../tests/Unit/ArchitectureTest.php), [AST probes](../tests/Unit/ArchitectureRulesTest.php), [strict contracts](../tests/Unit/StrictTypeContractsTest.php) | `composer test:isolated`; `isolated-tests`                                           | Included in the passing full suite: forbidden dependencies/floats/division and unsafe calls fail isolated probes; allowed integrations and strict caller behavior pass.                                    | **L**. Explicit namespaces/classes and selected syntax; no complete semantic/dataflow proof or bulk strict-type enforcement.                                                                     |
| Fail-closed ordinary test isolation         | Laravel **13.39.0**; [TestCase](../tests/TestCase.php), [phpunit.xml](../phpunit.xml), [guard tests](../tests/Unit/TestCaseTest.php)                                                                                    | `composer test:isolation`, then `composer test:isolated`                             | Configuration-only preflight **26 tests / 74 assertions**, exit 0, before full CI. Cached/inherited/URL/wrong-driver cases refuse before database resolution.                                              | **L**. Ordinary tests create only verified SQLite in-memory test data; persistent databases were not used.                                                                                       |
| PostgreSQL real-engine integrity            | Existing PostgreSQL **18.6** disposable harness; [lease](../tests/Support/DisposablePostgreSql.php), [runner](../tests/PostgreSqlIntegration.php), [guard tests](../tests/Unit/PostgreSqlEnvironmentTest.php)           | `composer postgres:guard`, opted-in `composer test:postgres`; `postgres-integration` | Configuration refusal tests passed within fresh Pest. Actual migrations, constraints, decimals, rollback/concurrency/populated upgrades: **historical EQ-07B, 18 checks / 114 assertions**, repeated runs. | **L guard / historical engine / U fresh engine**. No PostgreSQL mutation was authorized/run in EQ-11B. Do not weaken SQLite guards or infer all crash scenarios tested.                          |
| Authentication and authorization boundaries | Fortify **1.41.0**, typed resolver; [resolver tests](../tests/Unit/AuthenticatedUserResolverTest.php), [input tests](../tests/Feature/Auth/AuthenticationInputBoundaryTest.php)                                         | `composer test:isolated`; `isolated-tests`                                           | Fresh full Pest passed guest/wrong-principal/global-disagreement, ownership/tenant and malformed-email regressions, plus valid authentication flows.                                                       | **L**. Request identity is separate from resource authorization; tests do not replace penetration testing or operational rate-limit review.                                                      |
| Financial precision/history                 | Brick Math **1.0.0**; financial Actions/DTOs and [activity tests](../tests/Feature/Finance/FinancialActivityCalculatorTest.php)                                                                                         | `composer test:isolated`; `isolated-tests`                                           | Fresh full suite passed exact decimal, signs, corrections/reversals, currency separation, missing data and unchanged-observation contracts.                                                                | **L** for existing scenarios. Financial semantics/Team schema are IntelLedger-specific; no new analytics or reconciliation implemented.                                                          |
| Frontend owned-source quality               | TypeScript **5.9.3**, React **19.3.1**, Inertia React **3.9.1**, Vite Plus **1.3.0**; [tsconfig](../tsconfig.json), [Vite policy](../vite.config.ts)                                                                    | `composer quality:frontend`; `frontend-quality`                                      | Build, strict TS and lint/format passed; **36 Node tests**, including invalid Hooks/lint/format fixtures that fail without rewrites.                                                                       | **L**. Generated/vendor exclusions retain justification; no duplicate toolchain. Build reported the existing optional fontaine fallback warning; no package was installed.                       |
| Cross-browser component accessibility       | Playwright **1.64.1**, axe **4.14.0**; [config](../playwright.config.ts), [browser cases](../tests/Browser/shared-primitives.spec.ts)                                                                                   | `composer quality:browser`; `browser-accessibility`                                  | **Exit 0: 90 passed**, 30 per engine across light/dark projects, in **5.0 minutes**. Both intentional button-name-violation and missing-tooling probes confirmed command exit 1.                           | **L**. Synthetic components only; scoped NavigationMenu exception retains its prior explicit approval. WebKit is not manual native Safari or a screen reader.                                    |
| Real authenticated financial HTTP E2E       | Same browser tools, [E2E config](../playwright.e2e.config.ts), [capability adapter](../tests/Support/FinancialE2EEnvironment.php)                                                                                       | `composer e2e:guard`, opted-in `npm run test:e2e`; `financial-e2e`                   | Configuration/capability refusal regressions passed within fresh Pest. **Historical EQ-09B2B:** five real journeys plus interruption/refusal probes and cleanup.                                           | **L guard / historical journeys / U fresh HTTP**. No Laravel server or PostgreSQL fixtures were started in EQ-11B.                                                                               |
| Dependency vulnerability blocking           | Existing Composer/npm auditors; [aliases](../composer.json), [dependency contracts](../tests/Unit/DependencySecurityContractTest.php)                                                                                   | `composer security:check`; `dependency-security`                                     | **Exit 0**: no Composer advisories and npm **0 vulnerabilities** across configured types/severities. Existing simulated vulnerable-report/service-outage propagation tests passed in Pest.                 | **L**, time-specific. Includes dev/transitive exposure; abandoned dependencies are maintenance warnings. Runtime/OS and pinned Python ecosystem vulnerabilities are not covered by these audits. |
| Source security                             | Semgrep CE **1.182.1**, [15 local rules](../semgrep.yml), [pinned Python environment](../semgrep-requirements.txt)                                                                                                      | `composer source:check`; `source-security`                                           | First sandboxed attempt refused version execution, exit 2. Unchanged permitted rerun: **470 required files / 0 blocking findings**, **6 passing native probes**, exit 0.                                   | **L** after valid rerun; failed attempt remains recorded. Conservative heuristics, local-only scan; not a full security proof. No dependency/rule changes or suppression.                        |
| Current-tree secret detection               | Gitleaks **8.30.1**, [checksums](../gitleaks.lock.json), [wrapper](../tests/Support/secret_security.py)                                                                                                                 | `composer secret:tree`; `secret-security`                                            | As first phase of aggregate: **503-file available-source snapshot / 0 findings**. Extra toolkit/skill scan is recorded below because metadata-free snapshot roots exclude those directories.               | **L snapshot**, not a tracked-tree claim. Git-backed CI scans tracked files; ensure new standards/skills are committed before relying on it.                                                     |
| Workspace secret-security aggregate         | Same wrapper, [native probes](../tests/SecretSecurityTest.py)                                                                                                                                                           | `composer secret:check`; `secret-security`                                           | **Exit 2**, correctly refusing absent local Git history after the clean tree phase. `composer secret:test` separately passed **4 tests** covering detection/redaction/history/failure behavior.            | **B aggregate / L refusal and probes**. This command is not green locally; no fake metadata or bypass was introduced.                                                                            |
| Available remote Git history                | Same pinned Gitleaks, explicit temporary public `--mirror` clone, shallow=false                                                                                                                                         | `python -B tests/Support/secret_security.py history <verified temporary mirror>`     | **41 commits / 0 findings**, exit 0, across available mirrored refs; main SHA stated above. No history rewrite or working-tree checkout/execution.                                                         | **L remote-history scope**. Does not supply missing workspace history, unpublished changes, deleted/unreachable objects or hidden server refs.                                                   |
| CI failure and supply-chain contract        | [workflow](../.github/workflows/tests.yml), [gate/security probes](../tests/Unit/DependencySecurityContractTest.php)                                                                                                    | Final `ci` requires all nine jobs; contracts run through Pest                        | Fresh tests executed actual gate rejection for missing/failed/cancelled/skipped results and checked pins, permissions, install and service restrictions.                                                   | **L configured contract / U hosted current workflow**. Source policy is not enforced branch protection until published and required.                                                             |
| Dependency-update configuration             | [Dependabot](../.github/dependabot.yml); existing contract tests                                                                                                                                                        | Full Pest; configured Composer/npm/Actions weekly updates                            | Local configuration contracts passed. Read-only API observed Dependabot alerts/security updates disabled; branch/ruleset observations below.                                                               | **L local config / H disabled services**. Automatic updates/alerts cannot be claimed active from a YAML file.                                                                                    |
| Skill behavior and portability              | Codex CLI **0.165.0**, five instruction-only skills; [trial report](PORTABILITY.md)                                                                                                                                     | Bundled skill validator; five fresh-context explicit review trials                   | **5 structural passes + 5 actual independent reviews**. Expected source defects found without financial/Team/AWS contamination; seven target PHP syntax checks passed.                                     | **L bounded worker trials / B CLI timeout / U implicit copied-skill discovery**. Exact target profile and missing dependencies are explicit.                                                     |
| Operations and manual accessibility         | [AWS ADR](../ENGINEERING.md#eq-10a--aws-preview-architecture-and-operational-design), [manual AT checklist](../ENGINEERING.md#manual-screen-reader-checklist-not-yet-executed)                                          | Separately reviewed future runbooks; no deploy alias/gate claimed                    | Proposed architecture/retention/recovery and manual checklist inspected; no live deployment, restore, NVDA or VoiceOver execution.                                                                         | **D / U**, no M sign-off. AWS waits for core MVP and separate authorization; no HA/compliance claim.                                                                                             |

## Execution ledger and safety

The complete safe local aggregate passed before final documentation-only edits;
final reference/format/secret checks apply to those edits separately. The component
browser command passed all 90 cases and both failure probes. An explicit pinned
Gitleaks scan of **12 toolkit/skill documents** passed with zero findings, covering
the metadata-free snapshot's omitted directories. Local reference checks validated
**174 links/heading anchors**, **37 IntelLedger script aliases** and BookmarkBox's
separate `check:types` alias; five skill validators passed. Final formatting
checks passed across **187 files**. No
application, guard, workflow, dependency, migration or database configuration was
changed. The requested Pest suite used its existing verified in-memory database;
no persistent/leased PostgreSQL database was created or touched.

SHA-256 comparisons found **571 monitored maintained source/configuration/skill
files unchanged**, including locks, AGENTS/Boost/MCP, workflows and DB guards.
The initial snapshot also included 90 bindings in the already-ignored Wayfinder
actions/routes/wayfinder directories: the required build regenerated **39**.
Those generated writes are allowed by the existing command contract and are
reported separately; they are not claimed unchanged or excluded by a new rule.
No maintained application file or financial behavior was edited.

Cleanup verification found no listener on component/E2E ports **4178/4182**.
The identity-verified synthetic source fixture and public Git mirror were removed;
Windows read-only Git pack attributes required a scoped cleanup retry. No
persistent database, unrelated directory or other repository was removed.

The configured Semgrep command passed after native process permissions were
available. An additional unconfigured standalone version diagnostic stalled;
only its identity-verified three-process tree was terminated. No unrelated
process/database session was terminated and no scanner failure was hidden.

## Hosted observations and bounded GitHub hardening phase

Read-only GitHub CLI/API inspection of
`AnirudhVijayaraghavan/aniara-intelledger` established:

- Repository visibility is **public**. `main` protection endpoint returned
  **404: Branch not protected**, and repository rulesets returned an empty list.
- Dependabot vulnerability-alert endpoint returned **404: disabled**;
  `security_and_analysis.dependabot_security_updates.status` is **disabled**.
- Native secret scanning and push protection report **enabled**. Optional
  non-provider patterns/validity checks report disabled; no live credential or
  push-protection bypass test was attempted.
- [PR tests run 41031322356](https://github.com/AnirudhVijayaraghavan/aniara-intelledger/actions/runs/41031322356)
  failed at “Run CI Checks” using an older single `ci` job including “Setup
  Application”, on SHA `2e0f8d69b2672c886339732e93bd0378272df9b4`.
  [Push tests run 40199628764](https://github.com/AnirudhVijayaraghavan/aniara-intelledger/actions/runs/40199628764)
  also failed on the inspected main SHA. These are **H observed failures**, not
  runs of the local nine-job implementation. Other successful dynamic workflows
  are not substituted for the tests workflow.

No settings, workflow files on GitHub, branches or credentials were changed.
The separately authorized hardening phase should be bounded to:

1. Preserve a read-only settings/source snapshot and assign an owner/rollback
   plan. Reconcile a real Git checkout with this reviewed archive; publish the
   current local workflow/source through a reviewed PR, not a blind overwrite.
2. Obtain hosted results for **all nine jobs and final ci at the exact reviewed
   commit**. Verify Linux/container/browser cleanup and artifact integrity. Do
   not require an unavailable/new check or count old/dynamic workflows as proof.
3. Review the minimal main-branch ruleset: required `ci`, appropriate PR review,
   conversation resolution, force-push/deletion restrictions and explicit admin/
   break-glass bypass ownership. Configure only after approval and verify with a
   controlled authorized PR that failure cannot merge.
4. Enable/reverify Dependabot alerts and security updates, dependency graph and
   the reviewed three-ecosystem configuration; confirm notification/triage owners.
   Preserve existing native secret scanning/push protection and review bypasses.
5. Verify dependency-review availability/permissions before proposing its PR job.
   Public visibility alone does not prove an executed integration. Retain the
   mandatory lockfile audits regardless; avoid unrelated scanner expansion.
6. Record final settings and positive/negative hosted evidence, with an approved
   settings rollback/bypass procedure. No paid add-on, deployment credentials or
   AWS provisioning belongs to this phase without separate authorization.

## Risk and deferral register

| Item                                                          | Current disposition                                                | Next action / acceptance boundary                                                                                                                                                  |
| ------------------------------------------------------------- | ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Current workflow not hosted; old tests fail; main unprotected | **H observed / U current hosted enforcement**                      | Complete the separately approved publish/hosted/hardening checklist above. Local implementation closure is not hosted sign-off.                                                    |
| Dependabot alerts/security updates disabled                   | **H observed open**                                                | Enable only in the authorized settings phase; verify notifications and actual runs afterward.                                                                                      |
| Workspace lacks Git history                                   | **B aggregate**, partly compensated by **L remote 41-commit scan** | Reconcile full tracked source/history in a real checkout and run `composer secret:check` there. Do not treat the mirror as workspace provenance.                                   |
| Toolkit/skills outside metadata-free secret snapshot roots    | **Known scope limit**                                              | Targeted final scan below covers this delivery; Git-backed CI must include committed toolkit/skill files. No scanner roots/gates changed here.                                     |
| Standalone Codex CLI timed out                                | **B; implicit/CLI discovery U**                                    | Recheck bounded CLI execution in a functioning isolated environment. Fresh-context explicit worker trials already executed; structural validation is separate.                     |
| npm local/CI patch drift                                      | **L inventory difference: 12.2.1 vs 12.2.0**                       | Verify declared hosted version after publication; do not infer exact runtime parity or install/downgrade during this task.                                                         |
| Manual screen readers/native Safari/actual zoom/forced colors | **U / D**                                                          | Execute the documented NVDA/VoiceOver/native-browser checklist; automated WebKit/axe is not this evidence.                                                                         |
| Storybook peer compatibility                                  | **D, historical blocker**                                          | Dependency graph preserved; no install or forced peers. Re-evaluate compatibility/advisories in a separately authorized task, not as a claim that every future release is blocked. |
| Prior Docker cleanup item                                     | **L resolved for inspected engine**                                | Documented container name absent; original ID prefix `3a2ceb73fb51` also returned no containers. No removal performed; this does not inventory every volume/other Docker context.  |
| Semgrep Python dependency monitoring                          | **U / open**                                                       | Pins/installed consistency do not establish vulnerability monitoring. Review a bounded monitoring mechanism separately.                                                            |
| Broader DB deadlock/network/power-loss recovery               | **U beyond historical bounded tests**                              | Existing lease/concurrency evidence remains valid in its scope; design further tests without development-database access.                                                          |
| AWS/runtime/mail/private storage/retention/backup restoration | **D until core MVP**                                               | Review runtime package origin, workload credentials, key escrow, coordinated retention and actual restore proof before financial data. No provisioning or live recovery performed. |

## GH-01: hosted CI preparation blocked (2026-10-30)

**No current nine-job hosted result exists from this task.** Preparation could
verify GitHub access and remote state, but the supplied working archive still
has no `.git` metadata. `git rev-parse --show-toplevel`,
`git status --short --branch` and `git remote -v` each failed with “not a git
repository”. Its source branch, origin, HEAD and commit ancestry cannot be
established. A path to the full EQ checkout was requested; none was supplied
during this preflight. This is the metadata blocker explicitly anticipated by
GH-01, not an authentication failure or a hosted portability failure.

Read-only preparation verified:

- Authenticated identity `AnirudhVijayaraghavan`; repository
  `AnirudhVijayaraghavan/aniara-intelledger`, default branch `main`, repository
  push/admin permissions and OAuth scope names `gist`, `read:org`, `repo`,
  `workflow`. No credential value was printed or changed. A write was not attempted.
- Remote main HEAD remains `e0e48a307bacee64f02aa7d7f7d78536b5701e2c`.
  Its workflow blob is `86de1022fe3b6edd44cb382d48305609de6244c4`, containing only
  `ci` and still invoking `composer setup`. Local workflow blob is
  `f291e4dcc38b73b18b2519b6545bd80088d280d8`, SHA-256
  `0b5c0ce3bbc14e07dd28e25e6e7109a5a254afd2ae2687854e2d2a0fb140e8d2`.
  The old failed runs linked above do not test this implementation.
- Local YAML contains all nine requested jobs plus final `ci`; its `needs` list
  contains all nine. It specifies Ubuntu runners, PHP 8.4, Node 24, npm 12.2.0,
  read-only workflow permissions, locked installs and preserved test guards.
  PostgreSQL/E2E services use the explicitly disposable image/credentials with no
  persistent volumes or repository-secret expressions. These are **configuration
  observations**, not hosted runtime or credential-nondisclosure verification.

| Requested current hosted job | GH-01 result                           |
| ---------------------------- | -------------------------------------- |
| `backend-quality`            | Not run — source metadata blocker.     |
| `frontend-quality`           | Not run — source metadata blocker.     |
| `isolated-tests`             | Not run — source metadata blocker.     |
| `dependency-security`        | Not run — source metadata blocker.     |
| `source-security`            | Not run — source metadata blocker.     |
| `secret-security`            | Not run — source metadata blocker.     |
| `postgres-integration`       | Not run — source metadata blocker.     |
| `browser-accessibility`      | Not run — source metadata blocker.     |
| `financial-e2e`              | Not run — source metadata blocker.     |
| Final `ci`                   | Not run — no new PR/run/SHA to assess. |

No hardening branch, PR, push, old-workflow rerun or CI portability fix was made.
Runner memory, Linux installation/process behavior, native scanner/history
execution, browser installation and service cleanup remain unverified on the
current hosted implementation. No application, database, AWS or repository-setting
mutation occurred; only this evidence document and the milestone ledger changed.

### Exact resumption steps

Provide/open the **full Git checkout containing the reviewed EQ implementation**,
then verify it before publication:

```sh
git rev-parse --show-toplevel
git remote get-url origin
git status --short --branch
git log -1 --format="%H %s"
git rev-parse --is-shallow-repository
git fetch origin --prune --tags
git switch -c hardening/gh-01-hosted-ci
```

Run these inside that checkout, preserving its reviewed EQ commit ancestry. The
origin must match the verified repository; fetch full history if shallow. If no
full EQ checkout exists, create a separate full clone:

```sh
git clone https://github.com/AnirudhVijayaraghavan/aniara-intelledger.git <new-empty-directory>
```

Replace the directory placeholder before execution. That clone contains the
**older remote source**, not the uncommitted EQ implementation in this archive.
Reconcile the archive into it through an explicit reviewed file inventory/diff,
preserving origin/history and excluding `.env`, credentials, dependencies,
generated files, caches, private statements and test artifacts. Do not use
`git init` to invent provenance or copy the entire archive indiscriminately.
This source-reconciliation step must be resolved before claiming a verified
hardening branch; a workflow-only upload may omit required application/test code.

After the full reviewed implementation is staged explicitly on the hardening
branch, verify `git diff --check`, commit the intended changes, then publish via
the authorized PR path:

```sh
git commit -m "ci: prepare hosted engineering validation"
git push -u origin hardening/gh-01-hosted-ci
gh pr create --draft --repo AnirudhVijayaraghavan/aniara-intelledger --base main --head hardening/gh-01-hosted-ci --title "ci: verify engineering gates on hosted Ubuntu" --body "Verify all nine validation jobs and the fail-closed ci gate; no deployment or repository-setting changes."
gh run list --repo AnirudhVijayaraghavan/aniara-intelledger --workflow tests.yml --branch hardening/gh-01-hosted-ci --event pull_request --json databaseId,headSha,status,conclusion,url
```

The PR event is necessary: the current workflow's push trigger is restricted to
main. Inspect the returned **new run ID**, its PR/head and checkout SHA, all nine
job conclusions and final gate; do not reuse an older run ID. Collect sanitized
failed-step logs, actual runner image/memory and cleanup evidence. Apply only
observed CI portability fixes, rerun affected jobs and confirm the latest relevant
commit. Record the tested commit separately from later evidence-only edits.

GH-02 prerequisites remain a reviewed current-source PR with all nine hosted jobs
and final `ci` green, verified failure propagation and Linux/service cleanup,
full-history scanning and recorded runtime/resource evidence. Branch protection,
security-setting changes and deployment remain outside GH-01.

## Closure recommendation

Close the **local implementation phase** with this evidence and its explicit
deferrals once final document checks are recorded. Preserve the distinction:
local CI and available security/component checks can pass while the workspace
secret-history aggregate remains blocked. Do not report “every mandatory gate is
green” or claim hosted/production approval.

Hosted enforcement, production recovery, manual accessibility and standalone CLI
discovery remain separate acceptance work. The educational curriculum is prepared,
not delivered. No production deployment or real-financial-data readiness follows
from Enterprise Engineering Excellence v1 local closure.

## GH-01A: Hosted safety-test remediation (2026-10-30)

This supersedes the earlier **blocked preparation** status only for the now
available PR source. [PR #2](https://github.com/AnirudhVijayaraghavan/aniara-intelledger/pull/2)
uses `hardening/enterprise-engineering-v1`, initially inspected at
`c78737271a4ec8bc95b0735225915e6144e99ee0`.
[Run 41136929148](https://github.com/AnirudhVijayaraghavan/aniara-intelledger/actions/runs/41136929148)
failed `isolated-tests`, `postgres-integration` and `financial-e2e`; the other six
validation jobs passed and the final `ci` gate correctly failed. These are current
workflow failures, distinct from the older runs documented above.

The three failing assertions share a cold-checkout fixture defect. The two E2E
cleanup tests and the PostgreSQL unexpected-artifact test used nonrecursive
`mkdir` beneath an absent `.phpunit.cache`. Their unchecked writes consequently
created no fixture. E2E's `assertRun` correctly rejected `realpath($run) === false`; the
refusal test's `finally` then masked the missing-sentinel assertion. PostgreSQL's
idempotent cleanup correctly returned for the nonexistent directory. No unsafe
E2E invocation was accepted or incorrectly rejected by these failures.

`assertInvocation` consumes its explicit argument (the real runner passes
`getenv()`); its worker context and console/storage/view/asset overrides are
unchanged. PostgreSQL's separate environment collector still requires agreement
between `getenv()`, `$_ENV` and `$_SERVER`, and rejects cached/inherited targets.
The existing conflict test restores all three sources in `finally`; the new
fixture tests do not change environment variables. Canonical-path, lease,
OID/nonce/owner, account and HTTP-port checks remain intact.

The fixtures now create parents explicitly, assert successful setup and clean
their owned files in `finally`. The actual E2E entry point also checks recursive
private-directory creation. A separately reproduced cleanup defect was corrected:
all cache entries are validated before any unlink, and root/file symlinks are
explicitly refused. Thus a pre-existing unexpected file or nested directory
preserves the complete inspected evidence, including allowed cache files. Null
and already-removed paths remain safe idempotent no-ops. This is private-directory
validation, not a claim of atomic filesystem operations against malicious
concurrent same-user changes or recoverability after an unlink I/O failure.

Local evidence:

- A network-disabled, read-only PHP 8.4 **Debian Bookworm** container with a fresh
  temporary filesystem reproduced the original **3 failures / 78 passes** with
  the existing Pest files. After the fix: **85 passed / 161 assertions**.
- Windows `composer e2e:guard`: **27 passed / 40 assertions**;
  `composer postgres:guard`: **58 passed / 121 assertions**;
  `composer test:isolation`: **26 passed / 74 assertions**.
- Added regression cases first failed against the old cleanup because it removed
  `config.php` before refusing the later artifact. They now preserve it for
  unexpected files, nested directories and directories named `routes.php`.
- Isolated Linux probes rejected file, directory and dangling symlinks and retained
  evidence. This Windows account cannot create symlinks; no Windows symlink pass
  or skipped test is claimed. The Linux container is not an Ubuntu hosted pass.
- `composer ci:check`: **exit 0; 1,702 Pest tests / 11,727 assertions**, plus
  PHPStan level 8/Strict Rules, Rector, Pint, TypeScript, lint/format, 36 Node tests
  and production build. Explicit changed-file Pint also passes (`--dirty` requires
  Git metadata, unavailable in the working archive).
- `composer security:check`: **exit 0**, both dependency audits clean.
  `composer source:check`: **exit 0**, Semgrep 1.182.1 scans **470 required files /
  zero findings**; all **six** native failure probes pass. A direct PowerShell npm
  invocation hit execution-policy restrictions; invoking the installed `npm.cmd`
  shim correctly ran `npm.cmd run format:check`: **exit 0 / 187 files**.
- `composer test:postgres`: **18 checks / 114 assertions / verified cleanup** on a
  newly created, memory-backed service using CI's exact PostgreSQL 18.6 image
  digest. `composer test:e2e:probe` passes; `composer test:e2e`: **five Chromium
  journeys passed**, with verified cleanup for both. No development target used.
- After those checks the task-owned service reported **zero leased databases and
  zero lease-worker sessions**. It was stopped and removed without force; no
  private E2E/PostgreSQL cache directories or listener on port 4182 remained.
- Protected `.env`, lockfiles, workflow and SQLite TestCase hashes are unchanged.
  The final `ci` gate is untouched; all prior negative probes remain enabled.
  A separate PR-branch checkout matches all **583 tracked paths** in the working
  archive after applying only this reviewed patch (line-ending normalization).

Hosted follow-up: publish only the reviewed four PHP files and these two evidence
documents to PR #2's existing hardening branch. Verify the new run against its
actual source SHA; the earlier failing run is not a post-fix pass. The local Linux
reproduction used Debian, and does not establish hosted Ubuntu compatibility.
No branch protection, security settings, deployment or AWS changes are authorized.

### Hosted follow-up: POSIX supervisor ownership

The first fix was published to PR #2 as
`3c17eae032dca685470aba2a2bbfa00963242312`.
[Run 41141154435](https://github.com/AnirudhVijayaraghavan/aniara-intelledger/actions/runs/41141154435)
verified the original safety-test fixes on Ubuntu 24.04: `isolated-tests` and
`postgres-integration` passed, and the financial E2E configuration-only safety
step passed. Seven validation jobs passed. Two later failures kept `ci` red:

- Financial E2E reached **controlled HTTP child interruption**, then refused
  because the listener remained and cleanup could not be verified. A bounded
  synthetic listener reproduced the POSIX defect independently of Laravel/PG.
  Symfony Process sends SIGTERM only to its direct POSIX child; on Windows it
  uses `taskkill /T`. Default PHP signal termination bypasses the supervisor's
  `finally`, orphaning its HTTP child. This is distinct from the fixed cold-cache
  assertions. No unsafe cleanup or forced database drop was attempted.
- Browser accessibility independently reported one serious `color-contrast`
  violation on an outline button in WebKit/dark narrow-reflow testing: **89/90
  passed**. No component, axe rule, browser project or threshold was changed;
  this finding needs separate accessibility investigation if reproducible.

The POSIX-only correction registers SIGTERM/SIGINT handlers that unwind into the
existing supervisor cleanup. Its owned child gets a one-second stop budget,
inside the parent's existing two-second budget. Windows retains Symfony's native
tree termination. POSIX runners require `pcntl` and fail closed without it;
the isolated-tests and financial-e2e PHP setup steps now explicitly install that
bundled extension. These are the only workflow changes; the final gate, opt-ins,
lease verification and all quality/security thresholds remain unchanged.

The permanent regression uses an ephemeral loopback port, a readiness event and
one synthetic child with a ten-second accept deadline. It verifies successful
port rebinding after supervisor interruption, without Laravel, databases,
unrelated process termination or sleeps. It failed on Linux before the handler
and passes afterward. Windows E2E safety: **28/43**; fresh Linux safety classes:
**86 tests / 164 assertions**, without warnings. A local image compiled only PHP's
bundled pcntl extension to exercise the actual POSIX behavior; no Composer/npm
dependency or application runtime requirement was changed.

Complete local CI is rerun after this correction. Its result and the new hosted
run must be distinguished from the initial 1,702-test result above. The prior
claim of unchanged workflow hashes applies to the initial fixture-only fix;
the follow-up intentionally changes only the two PHP extension declarations.
