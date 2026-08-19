---
name: aniara-intelledger-design
description: Design and revise Aniara Intelledger's authenticated finance UI, public welcome page, and auth screens. Use when creating or changing React, Inertia, Blade, or Tailwind interface work that needs the product's calm, precise, institutional financial-ledger visual language.
---

# Aniara Intelledger Design

Build every screen as a trusted private finance ledger: calm, precise, operational, and polished. Prefer dense, scannable information over marketing-style decoration.

## Use the existing theme

- Prefer the semantic tokens in `resources/css/app.css`: `bg-background`, `text-foreground`, `bg-card`, `border-border`, `bg-primary`, `text-primary-foreground`, `bg-secondary`, `text-secondary-foreground`, `bg-accent`, `text-accent-foreground`, and `text-muted-foreground`.
- The visual palette is teal (`#2ec4b6`) primary, mint (`#cbf3f0`) secondary, soft orange (`#ffbf69`) accent, and orange (`#ff9f1c`) for restrained emphasis. Avoid hardcoded colors except when a one-off visual needs one of these palette values.
- Use the ledger serif stack: `Iowan Old Style`, `Palatino Linotype`, `Palatino`, `Georgia`, `Cambria`, `Times New Roman`, `serif`. Keep numbers easy to scan; do not use negative tracking or viewport-scaled type.
- For dark mode, use deep green-charcoal surfaces rather than black. Retain teal actions, controlled mint surfaces, and sparse orange accents.

## Compose the interface

- Build calm, dense operational layouts with full-width sections, panels, tables, and charts. Avoid oversized in-app hero text.
- Use cards only for repeated items, discrete panels, forms, and modals. Do not nest cards; keep radii `rounded-md` or smaller.
- Favor finance patterns: summary metric panels, ledger transaction rows, account lists with statuses, watchlist rows (symbol, price, change), simple chart placeholders, currency and region labels, ownership, and sync state.
- Use concise status labels such as `Synced`, `Manual`, `Market`, `Protected`, and `Snapshot`.
- Use `lucide-react` icons for actions, metrics, and finance concepts. Prefer icon-and-text buttons for commands.
- Keep responsive text and controls from overlapping at mobile and desktop widths.

## Screen direction

- **Welcome page:** Lead with `Aniara Intelledger`, one direct product statement, short professional copy, and a finance-oriented product surface. Do not add generic SaaS filler.
- **Auth:** Keep forms focused and compact. Use a financial side panel on desktop and a centered, simple form on mobile. Primary actions are teal; success and status messages use mint.
- **Dashboard:** Treat it as the operating surface for net worth, investments, cash runway, budgets, accounts, watchlists, syncs, and alerts. Until live data exists, render credible product-state placeholders.

## Motion and exclusions

- Use only subtle fades, small slide-ins, and hover color shifts. Do not use bouncing or flashy decorative motion.
- Avoid purple or blue-dominant palettes, heavy gradients, decorative orbs/bokeh, overly round cards, provider branding without a confirmed integration, and language that implies automated trading execution.
- If a choice feels playful, generic, or marketing-template-like, revise it toward a restrained institutional ledger.
