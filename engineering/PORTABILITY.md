# EQ-11B: skill execution and portability evidence

Assessment date: **2026-10-30**. See [VALIDATION.md](VALIDATION.md) for the final
control matrix and closure boundaries. This is a source-review demonstration,
not a second deployed application or a completed educational walkthrough.

## Execution method and discovery limits

All five skills passed the bundled skill-creator structural validator. More
importantly, five **independent fresh-context Codex review workers** actually
loaded the skills and performed bounded reviews. They received a normal review
request, the entrypoint and raw synthetic artifacts, without the expected
findings, proposed corrections or previous workers' conclusions.

Each worker confirmed that its skill appeared in its initial available catalog
at the IntelLedger repository location. Each explicitly loaded an exact-byte
copy in the isolated fixture and its relevant references. This proves catalog
availability and explicit invocation/behavior in those fresh contexts. It does
**not** prove implicit selection or automatic re-discovery of a copied skill in
a separately launched CLI installation.

A separate **Codex CLI 0.165.0** trial used `exec --ephemeral --ignore-user-config
--sandbox read-only --skip-git-repo-check`, disabled web search and approval
requests, selected the temporary fixture as its working root and requested a
JSON event stream/final message. It timed out after **180 seconds**, producing
no review. Only this first standalone trial was attempted; the other four were
tested through fresh-context workers. The timeout is not structural success or
evidence of CLI invocation. Its precise cause was not established. No sandbox
bypass, model override, dependency install or credential inspection was used.
[Official non-interactive execution contract](https://learn.chatgpt.com/docs/non-interactive-mode).

## Synthetic target

**BookmarkBox** is a small, non-financial Laravel source snapshot in a newly
created OS temporary directory, outside IntelLedger and other repositories. It
contains 21 initial files: user-owned bookmarks, two Actions, a controller, a
policy, routes, a React title form, manifests, a PHPStan configuration, a CI
proposal, an operations proposal, an adapted standards profile, adaptation
guidance and the five unchanged skill copies. Its own Git metadata is disposable;
it is not a copy of production data or ATLAS.

The target has **no Team/workspace, account ledger, currency, PostgreSQL service,
AWS architecture, installed vendor/Node dependencies, Boost runtime or test
harness**. Declared constraints are PHP `~8.4.1`, Laravel `^13.0`, PHPStan `^2.0`,
React `^19.0.0` and TypeScript `^5.0.0`; these are not installed-version evidence.
The target's aliases are `composer quality:backend` (syntax-checking one Action)
and `npm run check:types`; IntelLedger's aggregate/security commands do not exist.

Workers were restricted to file inspection: no writes, installs, application or
workflow execution, network, credentials, database, cloud or repository settings.
Hashes of all 21 files matched the initial manifest after the five reviews.
Seven standalone `php -l` checks subsequently passed on the six application PHP
files and route file. Syntax success does not establish framework behavior.

## Actual review results

| Skill / independent worker                                       | Observed findings and actionable recommendation                                                                                                                                                                                                                                             | Evidence qualification                                                                                                                                                 |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `engineering-php-review` / `eq11b_php_trial`                     | Identified request-principal handoffs to typed User Action parameters in `BookmarkController.php:11,15`, the one-file syntax-only quality alias, and the missing archive authorization. Recommend real principal narrowing, meaningful analyzer coverage and invoking the ownership policy. | Correctly qualified nullable/payload contracts as requiring analyzer/runtime verification; did not fabricate a PHPStan diagnostic or guest bypass.                     |
| `engineering-domain-review` / `eq11b_domain_trial`               | Traced authenticated route → global bound bookmark → Action ignoring its actor → update. `BookmarkPolicy.php:6–7` defines ownership but the path never invokes it. Recommend policy enforcement and owner/nonowner state-preservation regressions.                                          | Distinguished this source-observed authorization defect from a conditional concurrent timestamp-rewrite risk; did not invent a financial audit or Team requirement.    |
| `engineering-ci-security-review` / `eq11b_ci_trial`              | Found `checks.yml:12–16` final `always()` job only echoes completion, so it cannot propagate dependency failure; also found missing checkout/install and unused declared analysis/TypeScript coverage. Recommend an explicit fail-closed gate and target-specific required checks.          | No claim of hosted execution, installed scanner coverage, advisory findings or branch protection from source alone.                                                    |
| `engineering-accessibility-review` / `eq11b_accessibility_trial` | Found `BookmarkForm.tsx:6–8` error text not referenced by the field, missing invalid state and Clear button removed from sequential keyboard order. Recommend preserving help plus conditional error IDs, invalid state and native Tab reachability.                                        | Did not invent contrast results, executed axe scans or WCAG compliance. Fixed-ID collision was correctly conditional on multiple instances, not a demonstrated defect. |
| `engineering-production-review` / `eq11b_production_trial`       | Found unsupported HA/zero-loss claims in `OPERATIONS.md:2–7`, same-host-only backup/key custody, absent restore evidence and inadequate readiness/release proof. Recommend explicit objectives, independent recovery and a bounded rehearsal.                                               | Declined provider pricing or an AWS architecture; `/up` behavior was identified as a proposal claim, not a tested response.                                            |

All five loaded their copied SKILL.md, the target's `engineering/README.md` and
`engineering/ADAPTING.md`. Reviews correctly separated manifest constraints from
installed packages, target rules from IntelLedger's conventions, and review
procedures from Boost's framework guidance. Boost was absent; no fake MCP/API
execution or tool success was asserted. Recommendations were checked against the
actual fixture source; no target fixes were executed.

These are successful **bounded behavioral trials**, not a statistical quality
score, independent penetration test or evidence that every future review will
find all defects. The workers used one task family and the same installed skill
versions. CLI auto-discovery, implicit routing and broader cross-project behavior
remain open verification.

## Portability defects and corrections

- **Adapted heading lost:** the target's shortened standards profile initially
  omitted `mandatory-command-and-ci-contract`, linked by the CI skill. That worker
  identified the broken anchor. The profile heading was corrected after the
  reviews, then all **17 skill links** resolved. This was a target adaptation
  defect, not a broken link in IntelLedger's original skills.
- **Relative-base confusion:** two workers initially tried the wrong reference
  base and then corrected it themselves. [ADAPTING.md](ADAPTING.md#porting-the-skills)
  now explicitly resolves links from the directory containing SKILL.md and
  requires preserving or remapping every referenced heading before trial use.
- **Missing dependencies:** the target had no analyzer/framework/browser runtime.
  Every worker kept those results unverified rather than installing packages or
  importing IntelLedger's vendor directory. A manifest or copied configuration
  does not supply executable enforcement.
- **Hardcoded assumptions:** IntelLedger command names, financial namespaces,
  Team tenancy, money/audit semantics, database leases and AWS deployment choices
  require target mapping. The review skills did not impose them on bookmarks.
  No absolute Windows paths occur in the five SKILL.md entrypoints; their three-
  level relative links intentionally depend on copying/adapting `engineering/`.
- **Toolchain transfer:** Java/Spring mappings remain conceptual. No Java tools,
  platform compatibility or equivalent runtime checks were executed. React
  semantics transfer independently of Inertia, Tailwind, Radix and product branding.

The existing skills needed no behavioral rewrite or new dependency. The reusable
documentation correction is narrow and evidence-driven. Future trials should
cover a different task family and actual target-installed tools, with separate
authorization for execution. No permanent example application or executable
framework was added by this assessment.
