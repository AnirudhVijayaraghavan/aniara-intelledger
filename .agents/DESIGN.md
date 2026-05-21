# Aniara Intelledger Design Direction

This document captures the visual direction for Aniara Intelledger. Use it when creating or revising UI so the application stays consistent as it grows.

## Design Intent

Aniara Intelledger should feel like a respected financial ledger: calm, precise, trustworthy, and operational. The app is not a marketing toy and should not feel overly decorative. It should feel suitable for personal wealth, family finance, banking, investments, budgets, and market analysis.

The product may later become a SaaS, so screens should feel polished and professional from the beginning.

## Palette

Primary light-mode palette:

```txt
Orange: #ff9f1c
Soft Orange: #ffbf69
White: #ffffff
Mint: #cbf3f0
Teal: #2ec4b6
```

Current theme token direction:

```txt
background: white
foreground: deep green-black
primary: teal
secondary: mint
accent: soft orange
muted: very pale mint
border: pale teal
```

Dark mode should not become flat black. Use deep green-charcoal backgrounds, teal primary actions, mint secondary surfaces, and controlled orange accents.

## Typography

Use a ledger-like serif stack for the app:

```txt
Iowan Old Style
Palatino Linotype
Palatino
Georgia
Cambria
Times New Roman
serif
```

The goal is an older institutional ledger feel, not a decorative vintage poster. Text should remain readable and restrained.

Use:

- Serif type for product UI and headings.
- Clear numeric hierarchy for balances, prices, and totals.
- No negative letter spacing.
- No viewport-scaled font sizes.
- Compact headings inside dashboard cards and panels.

## Layout Principles

Prefer dense but calm operational layouts:

- Dashboards should prioritize scanning and comparison.
- Use clear sections, tables, panels, and charts.
- Avoid oversized marketing sections inside the authenticated app.
- Avoid nested cards.
- Use cards only for repeated items, individual panels, forms, and modals.
- Keep card radius at `rounded-md` or smaller.
- Use full-width sections or unframed layouts for page structure.
- Keep text from overlapping at mobile and desktop widths.

## Interaction Style

Use subtle animation:

```txt
fade-in
small slide-in
hover color shifts
no bouncing
no flashy decorative motion
```

Use icons from `lucide-react` for action buttons, metric labels, and finance concepts.

Prefer icon+text buttons for commands. Use familiar icons for common tools.

## Financial UI Patterns

Core finance screens should use:

- Summary metric panels.
- Ledger-style transaction rows.
- Account lists with status labels.
- Watchlist rows with symbols, prices, and changes.
- Simple bar or line chart placeholders until real charting lands.
- Currency and region labels where relevant.
- Clear ownership and sync status labels.

Example labels:

```txt
Synced
Manual
Market
Protected
Snapshot
```

## Landing Page Direction

The public welcome page should:

- Lead with `Aniara Intelledger`.
- Explain the app in one direct product statement.
- Show a finance-oriented visual surface, not a generic illustration.
- Keep copy short and professional.
- Avoid filler sections.
- Use the teal/mint/orange palette.
- Include simple motion only.

## Auth Screens Direction

Auth screens should feel secure and financial:

- Use the same ledger palette and serif type.
- Keep forms focused and compact.
- Use a financial side panel on desktop.
- Keep mobile auth simple and centered.
- Use primary teal buttons.
- Use mint success/status messages.

## Dashboard Direction

The dashboard should become the operating surface for:

- Net worth.
- Investments.
- Cash runway.
- Budgets.
- Accounts.
- Watchlists.
- Upcoming syncs and alerts.

Until real data exists, placeholders should look like realistic product states, not abstract starter-kit blocks.

## Components And Tokens

Use theme tokens from `resources/css/app.css` whenever possible:

```txt
bg-background
text-foreground
bg-card
border-border
bg-primary
text-primary-foreground
bg-secondary
text-secondary-foreground
bg-accent
text-accent-foreground
text-muted-foreground
```

Avoid hardcoded colors unless a one-off visual requires a palette color from this document.

## What To Avoid

- Generic SaaS landing-page filler.
- Purple or blue-dominant palettes.
- Heavy gradients.
- Decorative orbs or bokeh backgrounds.
- Over-rounded cards.
- Nested card layouts.
- Huge hero type inside app dashboards.
- Unofficial financial provider branding unless integration is confirmed.
- AI trading language that implies automated buy/sell execution.

## Standing Rule

Every new financial screen should look like it belongs to a private, trusted finance ledger. If a design choice makes the app feel playful, generic, or like a marketing template, revise it toward a calmer institutional style.
