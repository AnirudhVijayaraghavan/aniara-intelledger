# Aniara Intelledger Project Direction

This document captures the agreed product and architecture direction for Aniara Intelledger. Treat it as context for future planning and implementation decisions.

## Product Vision

Aniara Intelledger is a financial ledger and wealth operating system for USA and India accounts. It starts as a personal and family finance app, but should be built with SaaS-quality architecture from the beginning.

The app should eventually support:

- Bank account tracking across USA and India.
- Expense tracking and categorization.
- Budgeting and recurring spending analysis.
- Net worth tracking for individuals and households.
- Brokerage and investment portfolio tracking.
- Watchlists for US and Indian securities.
- Market data views similar to Google Finance.
- Stock market APIs, quotes, charts, RSS/news feeds, and signals.
- AI-assisted financial analysis and educational robo-advisor-style insights.
- Manual entry and imports where bank APIs are unavailable.
- Family/household access so multiple family members can manage financial data online.

The app may become a SaaS product, so ownership boundaries, authorization, auditability, and data security should be designed up front.

## Architecture Direction

Use Laravel as a modular monolith first. Do not start with microservices.

Organize backend code by domain:

```txt
app/
  Domain/
    Identity/
    Households/
    Accounts/
    Transactions/
    Budgets/
    Investments/
    MarketData/
    NetWorth/
    Documents/
    Integrations/
    AiAdvisory/
    Compliance/
    Billing/
```

Each domain should own its models, actions, data objects, enums, events, jobs, services, policies, and query classes where needed.

Example domain structure:

```txt
app/Domain/Investments/
  Actions/
    SyncPortfolioHoldings.php
    CalculatePortfolioPerformance.php
  Data/
    HoldingData.php
    SecurityQuoteData.php
  Enums/
    AssetClass.php
    BrokerageProvider.php
  Events/
    HoldingsSynced.php
  Jobs/
    SyncBrokerageAccountJob.php
  Models/
    Portfolio.php
    Holding.php
    Security.php
  Policies/
    PortfolioPolicy.php
  Services/
    PortfolioValuationService.php
  Integrations/
    PlaidInvestmentConnector.php
    RobinhoodConnector.php
  Queries/
    PortfolioPerformanceQuery.php
```

## Backend Patterns

Use thin controllers. Prefer single-action controllers for meaningful user actions, but do not force every simple CRUD screen into single-action controllers if a resource controller is clearer and still thin.

Use Form Request classes for validation and request authorization.

Use Policies for model and household-level authorization.

Use Actions for business operations:

```txt
CreateManualAccount
ImportStatementTransactions
CategorizeTransaction
CreateBudget
SyncExternalAccount
CalculateNetWorthSnapshot
```

Use Query classes for complex reads:

```txt
AccountBalanceTimelineQuery
MonthlySpendingByCategoryQuery
PortfolioAllocationQuery
FamilyNetWorthQuery
```

Do not create generic repositories for every Eloquent model. Eloquent is already the primary data access abstraction. Use repositories or provider interfaces only when they add real value, especially for:

- External financial providers.
- Swappable infrastructure.
- Complex reporting/read models.
- Market data providers.

External integrations should sit behind contracts:

```php
interface BankDataProvider
{
    public function accounts(Connection $connection): AccountCollection;

    public function transactions(Connection $connection, DateRange $range): TransactionCollection;
}

interface InvestmentDataProvider
{
    public function holdings(Connection $connection): HoldingCollection;

    public function transactions(Connection $connection, DateRange $range): InvestmentTransactionCollection;
}

interface MarketDataProvider
{
    public function quote(Symbol $symbol): QuoteData;

    public function candles(Symbol $symbol, DateRange $range): CandleCollection;
}
```

## Core Domains

Identity:
Users, auth, email verification, 2FA, passkeys, profile, device sessions.

Households:
Family groups, member roles, invitations, SaaS ownership boundary.

Accounts:
Banks, brokerages, wallets, loans, credit cards, and manual accounts.

Transactions:
Income, expenses, transfers, categorization, recurring rules.

Budgets:
Monthly budgets, category limits, forecasts, alerts.

Investments:
Portfolios, holdings, securities, trades, dividends, cost basis.

MarketData:
Quotes, candles, symbols, exchanges, FX rates, corporate actions.

NetWorth:
Assets, liabilities, snapshots, historical wealth tracking.

Integrations:
Plaid, broker connectors, ICICI, India Account Aggregator, CSV imports, statement uploads.

Documents:
Statements, receipts, tax docs, uploaded files, parsed artifacts.

AiAdvisory:
AI summaries, explanations, spending insights, portfolio risk analysis, watchlist briefings.

Compliance:
Audit logs, consent records, advisory disclaimers, data retention.

Billing:
Plans, subscriptions, usage limits, SaaS billing.

## Data Ownership

Use `household_id` as the primary ownership boundary for financial records. Use `user_id` for creator, actor, or personal ownership fields where appropriate.

Most financial records should be household-scoped:

```txt
accounts
transactions
budgets
portfolios
holdings
watchlists
documents
net_worth_snapshots
provider_connections
```

Every household-owned model needs policies and tests proving users cannot access data from another household.

## Initial Data Model Direction

Expected core tables:

```txt
users
households
household_user
institutions
accounts
account_connections
transactions
transaction_categories
transaction_rules
budgets
budget_lines
portfolios
holdings
securities
security_prices
watchlists
watchlist_items
net_worth_snapshots
exchange_rates
documents
imports
provider_sync_runs
audit_logs
ai_conversations
ai_advice_outputs
consents
```

## Frontend Direction

Use Inertia React with product-area modules. Pages compose feature components. Feature components should use Wayfinder route/action imports instead of hardcoded URLs.

Recommended structure:

```txt
resources/js/
  pages/
    dashboard.tsx
    accounts/
      index.tsx
      show.tsx
      create.tsx
    transactions/
      index.tsx
      import.tsx
    budgets/
      index.tsx
      show.tsx
    investments/
      portfolio.tsx
      watchlists.tsx
      market.tsx
    net-worth/
      index.tsx
    settings/
      security.tsx

  features/
    accounts/
      components/
      hooks/
      types.ts
    transactions/
      components/
      hooks/
      types.ts
    investments/
      components/
      charts/
      types.ts
    market-data/
      components/
      types.ts

  components/
    ui/
    layout/
    data-table/
    charts/
```

Use Inertia deferred props, partial reloads, and polling carefully for expensive data such as market prices, portfolio analytics, and net worth charts.

## Integrations Direction

USA:
Plaid is a likely first integration for bank account data and investment holdings. Use a separate market data provider for live or delayed quotes, since account aggregation data should not be treated as a real-time market data source.

Robinhood:
Be careful with Robinhood. Do not build core SaaS features on unofficial APIs. Use official APIs only where available and stable.

India:
Use manual accounts, CSV import, and statement parsing first. Investigate India's Account Aggregator framework and ICICI APIs for later integration. Assume India support will need provider-by-provider research and may remain manual in places.

## AI Advisory Direction

Do not start with automated trading or direct buy/sell execution.

Start with AI features that are explanatory and educational:

```txt
Portfolio summaries
Risk explanations
Portfolio concentration warnings
Spending insights
Budget suggestions
News and RSS summaries
Signal explanations
Watchlist briefings
```

Use Laravel AI SDK agents when implementing AI features:

```txt
app/Ai/Agents/
  PortfolioAnalyst.php
  BudgetCoach.php
  MarketNewsSummarizer.php
  RiskExplainer.php

app/Ai/Tools/
  GetPortfolioHoldings.php
  GetRecentTransactions.php
  GetMarketNews.php
  GetSecurityQuote.php
```

AI outputs must be stored, versioned, and auditable. Record at least:

```txt
model
provider
input snapshot
tools used
output
risk disclaimer
requesting user
timestamp
```

Treat investment advice as a regulated domain. Until legal review is complete, AI should provide educational analysis and risk context, not personalized trade instructions or automated trading.

## Security Baseline

Financial data is extremely sensitive. Baseline requirements:

```txt
2FA and passkeys
email verification
device/session management
encrypted provider tokens
audit logs for sensitive actions
policy checks on every household-owned model
no provider secrets in frontend props
rate limits on auth and sync endpoints
background jobs for external syncs
verified webhooks where providers support them
```

Use Fortify features where possible for authentication, 2FA, passkeys, password confirmation, and email verification.

## Jobs And Scheduling

All external syncs should run through queues:

```txt
SyncConnectionAccountsJob
SyncAccountTransactionsJob
SyncInvestmentHoldingsJob
FetchMarketQuotesJob
CalculateNetWorthSnapshotJob
ParseUploadedStatementJob
GenerateAiPortfolioBriefJob
```

Scheduled work should include:

```txt
daily net worth snapshots
overnight holdings sync
market close price ingestion
weekly AI household summary
budget rollover
stale connection detection
```

Use `provider_sync_runs` to track every sync attempt, status, error, duration, and provider response metadata.

## SaaS Readiness

Build personal-first, but preserve SaaS options:

```txt
household tenancy
role-based household access
plan limits
provider connection limits
data export
account deletion
audit trails
consent records
billing
admin/support access controls
```

Avoid full enterprise complexity until needed, but do not skip ownership boundaries.

## Recommended Build Order

1. Foundation: households, roles, auth hardening, audit logs.
2. Manual finance: manual accounts, transactions, categories, budgets.
3. Net worth: assets, liabilities, snapshots, charts.
4. Imports: CSV/manual statement upload.
5. US integrations: bank and investment connection.
6. Market data: securities, quotes, watchlists, portfolio views.
7. India manual support: ICICI/manual accounts, INR/USD FX, Indian asset types.
8. India integrations: Account Aggregator/API research and connector.
9. AI insights: summaries and explanations only.
10. SaaS layer: billing, onboarding, limits, compliance review.

## Standing Engineering Guidance

- Prefer Laravel conventions and existing project patterns.
- Use Actions for business behavior and Query classes for complex reads.
- Keep controllers thin.
- Use Form Requests for validation.
- Use Policies for authorization.
- Use Jobs for external sync, parsing, AI work, and slow calculations.
- Use Events for meaningful domain changes.
- Avoid generic repositories unless there is a real provider or infrastructure boundary.
- Use Wayfinder in frontend code instead of hardcoded backend URLs.
- Add tests for every behavior change, especially household authorization and financial calculations.
- Keep AI and advisory features auditable from the start.
