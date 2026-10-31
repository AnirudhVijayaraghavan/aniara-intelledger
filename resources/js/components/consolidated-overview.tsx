import { Link } from '@inertiajs/react';
import { AlertCircle, ArrowLeftRight, Settings2 } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatUtcTimestamp, isPriorDate } from '@/lib/financial-dates';
import { formatDecimal } from '@/lib/format-decimal';
import { index as accountsIndex } from '@/routes/accounts';
import { index as ratesIndex } from '@/routes/exchange-rates';
import { edit as teamSettings } from '@/routes/teams';
import type {
    ConsolidatedNetWorthOverview,
    FinancialTeam,
} from '@/types/finance';

export default function ConsolidatedOverview({
    overview,
    team,
    canManageRates,
}: {
    overview: ConsolidatedNetWorthOverview;
    team: FinancialTeam;
    canManageRates: boolean;
}) {
    const quotes = Object.values(overview.fxQuotes).filter(
        (quote) => quote.rateId !== null,
    );
    const missingAccounts = Object.values(overview.original.currencies).flatMap(
        (currency) =>
            currency.accounts.filter((account) => account.missingData),
    );
    const hasAccounts = Object.keys(overview.original.currencies).length > 0;
    const metrics = [
        { label: 'Consolidated net worth', amount: overview.netWorth },
        { label: 'Total assets', amount: overview.totalAssets },
        { label: 'Total liabilities', amount: overview.totalLiabilities },
    ];

    return (
        <section
            aria-labelledby="consolidated-heading"
            className="min-w-0 overflow-hidden rounded-md border bg-card text-card-foreground"
        >
            <header className="flex flex-wrap items-start justify-between gap-4 border-b px-5 py-4">
                <div className="grid gap-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 id="consolidated-heading" className="font-semibold">
                            Reporting-currency valuation
                        </h2>
                        <Badge
                            variant={
                                overview.isComplete ? 'secondary' : 'outline'
                            }
                        >
                            {!hasAccounts
                                ? 'Awaiting accounts'
                                : overview.isComplete
                                  ? 'Complete'
                                  : 'Incomplete'}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Reporting currency:{' '}
                        <strong className="font-medium text-foreground">
                            {overview.reportingCurrencyCode}
                        </strong>{' '}
                        · As of{' '}
                        <time dateTime={overview.original.asOfDate}>
                            {overview.original.asOfDate}
                        </time>{' '}
                        (end of day UTC)
                    </p>
                </div>
                <Button asChild variant="ghost" size="sm">
                    <Link href={teamSettings(team.slug)}>
                        <Settings2 />
                        Reporting currency
                    </Link>
                </Button>
            </header>
            {!overview.isComplete && (
                <div className="px-5 pt-5">
                    <Alert className="rounded-md border-accent bg-accent/15">
                        <AlertCircle />
                        <AlertTitle className="line-clamp-none tracking-normal">
                            Consolidated totals are unavailable
                        </AlertTitle>
                        <AlertDescription>
                            {missingAccounts.length > 0 && (
                                <p>
                                    {missingAccounts.length}{' '}
                                    {missingAccounts.length === 1
                                        ? 'account is'
                                        : 'accounts are'}{' '}
                                    missing a valid balance observation.{' '}
                                    <Link
                                        href={accountsIndex(team.slug)}
                                        className="font-medium underline underline-offset-4"
                                    >
                                        Review account balances
                                    </Link>
                                    . Original known balances are shown below.
                                </p>
                            )}
                            {overview.missingRateCurrencies.length > 0 && (
                                <div className="grid gap-2">
                                    <p>
                                        No eligible historical FX rate is
                                        available for:
                                    </p>
                                    <ul className="grid gap-2">
                                        {overview.missingRateCurrencies.map(
                                            (code) => (
                                                <li key={code}>
                                                    <span>
                                                        {code} →{' '}
                                                        {
                                                            overview.reportingCurrencyCode
                                                        }
                                                        .{' '}
                                                    </span>
                                                    <Link
                                                        href={ratesIndex(
                                                            team.slug,
                                                            {
                                                                query: {
                                                                    base_currency_code:
                                                                        code,
                                                                    quote_currency_code:
                                                                        overview.reportingCurrencyCode,
                                                                },
                                                            },
                                                        )}
                                                        className="font-medium underline underline-offset-4"
                                                    >
                                                        {canManageRates
                                                            ? 'Record a manual rate'
                                                            : 'View FX rates'}
                                                    </Link>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                    {!canManageRates && (
                                        <p>
                                            A household owner or administrator
                                            can record the missing rates.
                                        </p>
                                    )}
                                </div>
                            )}
                        </AlertDescription>
                    </Alert>
                </div>
            )}
            <dl className="grid divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                {metrics.map((metric) => (
                    <div key={metric.label} className="min-w-0 px-5 py-5">
                        <dt className="text-sm text-muted-foreground">
                            {metric.label}
                        </dt>
                        <dd className="mt-2 text-xl font-medium break-all tabular-nums md:text-2xl">
                            {overview.isComplete &&
                            hasAccounts &&
                            metric.amount !== null
                                ? formatDecimal(
                                      metric.amount,
                                      overview.reportingCurrencyCode,
                                  )
                                : '—'}
                        </dd>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {!hasAccounts
                                ? 'Add accounts to build this valuation'
                                : overview.isComplete
                                  ? overview.reportingCurrencyCode
                                  : 'Not valued until missing data is resolved'}
                        </p>
                    </div>
                ))}
            </dl>
            <div className="grid gap-3 border-t px-5 py-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h3 className="text-sm font-medium">
                        FX observations used
                    </h3>
                    <Button asChild variant="outline" size="sm">
                        <Link href={ratesIndex(team.slug)}>
                            <ArrowLeftRight />
                            FX rates
                        </Link>
                    </Button>
                </div>
                <p className="text-sm text-muted-foreground">
                    Manual rates are not refreshed automatically. Prior-date
                    rates may be stale; review their effective timestamps.
                </p>
                {quotes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {overview.missingRateCurrencies.length > 0
                            ? 'Required FX observations have not been supplied.'
                            : 'No currency conversion is required for the available balances.'}
                    </p>
                ) : (
                    <ul className="grid divide-y">
                        {quotes.map((quote) => (
                            <li
                                key={quote.fromCurrencyCode}
                                className="grid gap-2 py-3"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-3">
                                    <span className="text-sm font-medium break-all tabular-nums">
                                        1 {quote.baseCurrencyCode} ={' '}
                                        {formatDecimal(
                                            quote.rate,
                                            quote.quoteCurrencyCode,
                                        )}{' '}
                                        {quote.quoteCurrencyCode}
                                    </span>
                                    <span className="flex flex-wrap gap-2">
                                        <Badge variant="secondary">
                                            {quote.source === 'manual'
                                                ? 'Manual'
                                                : quote.source}
                                        </Badge>
                                        {quote.effectiveAt &&
                                            isPriorDate(
                                                quote.effectiveAt,
                                                overview.valuationAt,
                                            ) && (
                                                <Badge variant="outline">
                                                    Prior-date · May be stale
                                                </Badge>
                                            )}
                                    </span>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {quote.fromCurrencyCode} →{' '}
                                    {quote.toCurrencyCode}
                                    {quote.isInverse
                                        ? ' · Used inversely'
                                        : ' · Direct rate'}{' '}
                                    · Observation #{quote.rateId}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {quote.effectiveAt && (
                                        <>
                                            Effective{' '}
                                            <time dateTime={quote.effectiveAt}>
                                                {formatUtcTimestamp(
                                                    quote.effectiveAt,
                                                )}
                                            </time>
                                        </>
                                    )}
                                    {quote.recordedAt && (
                                        <>
                                            {' '}
                                            · Recorded{' '}
                                            <time dateTime={quote.recordedAt}>
                                                {formatUtcTimestamp(
                                                    quote.recordedAt,
                                                )}
                                            </time>
                                        </>
                                    )}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
