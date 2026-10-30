---
name: engineering-accessibility-review
description: Review React component accessibility, keyboard/focus behavior, semantic tokens, and WCAG evidence without redesigning the product or assuming Storybook.
---

# React accessibility and design-system review

Read the [frontend standard](../../../engineering/README.md#react-typescript-and-accessibility)
and [review contract](../../../engineering/README.md#agent-assisted-review-contract).
Use the [adaptation guide](../../../engineering/ADAPTING.md) for another app's
components, tokens and test infrastructure; do not copy IntelLedger branding.

Inspect the affected owned primitive and its call sites, theme tokens and existing
test harness. For implementation work, follow the repository's product-design
skill and relevant Boost framework skills where available. Check actual dependency
versions before relying on React/Inertia/Radix APIs or proposing tooling.

Review accessible names, native semantics, stable label/help/error IDs, combined
descriptions, invalid/announcement states, keyboard order and visible focus.
Exercise dialog/menu focus entry/containment/Escape/restoration and disabled states
where applicable. Evaluate light/dark contrast using actual foreground/background
compositing and relevant states, not a palette swatch alone. Preserve decimal-safe
presentation and existing financial calculation ownership.

Prefer existing component browser/axe and native utility tests with synthetic
fixtures. Distinguish component-only, actual authenticated E2E and manual assistive
technology evidence. Run bounded available engines and meaningful interactive
states; report unavailable engines instead of skipping them silently. Do not
suppress axe findings, generalize a documented scoped-scan exception or force
incompatible Storybook peers to obtain green results.

Report criteria/interaction affected, reproducible evidence and minimal correction.
State browser/theme/viewport coverage and outstanding NVDA/VoiceOver/native Safari/
zoom checks. Automated success does not establish full WCAG compliance or verified
screen-reader compatibility. If fixes are authorized, add real behavior assertions
and preserve the existing product design and validation contracts.
