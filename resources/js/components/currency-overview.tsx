import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDecimal } from '@/lib/format-decimal';
import { show } from '@/routes/accounts';
import type {
    AccountNetWorthContribution,
    CurrencyNetWorth,
    FinancialTeam,
} from '@/types/finance';

function TypeAllocation({
    title,
    amounts,
    currency,
}: {
    title: string;
    amounts: Record<string, string>;
    currency: CurrencyNetWorth;
}) {
    const entries = Object.entries(amounts);

    return (
        <div className="min-w-0">
            <h3 className="mb-3 text-sm font-medium">{title}</h3>
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No recorded observations in this category.
                </p>
            ) : (
                <dl className="grid gap-3">
                    {entries.map(([type, amount]) => (
                        <div
                            key={type}
                            className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm"
                        >
                            <dt className="text-muted-foreground">
                                {currency.accounts.find(
                                    (account) => account.type === type,
                                )?.typeLabel ?? type}
                            </dt>
                            <dd className="min-w-0 break-all tabular-nums">
                                {formatDecimal(amount, currency.currencyCode)}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </div>
    );
}

export default function CurrencyOverview({
    currency,
    team,
    canManageAccounts,
}: {
    currency: CurrencyNetWorth;
    team: FinancialTeam;
    canManageAccounts: boolean;
}) {
    const institutions = new Map<
        number | null,
        { name: string; accounts: AccountNetWorthContribution[] }
    >();
    for (const account of currency.accounts) {
        let institution = institutions.get(account.institutionId);
        if (!institution) {
            institution = {
                name: account.institutionName ?? 'No institution recorded',
                accounts: [],
            };
            institutions.set(account.institutionId, institution);
        }
        institution.accounts.push(account);
    }
    const hasKnownBalances = currency.accounts.some(
        (account) => !account.missingData,
    );
    const metrics = [
        {
            label: currency.isComplete ? 'Total assets' : 'Known assets',
            value: currency.totalAssets,
        },
        {
            label: currency.isComplete
                ? 'Total liabilities'
                : 'Known liabilities',
            value: currency.totalLiabilities,
        },
        {
            label: currency.isComplete ? 'Net worth' : 'Known net worth',
            value: currency.netWorth,
        },
    ];

    return (
        <section
            aria-labelledby={`currency-${currency.currencyCode}`}
            className="min-w-0 overflow-hidden rounded-md border border-border bg-card text-card-foreground"
        >
            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div className="flex flex-wrap items-center gap-3">
                    <h2
                        id={`currency-${currency.currencyCode}`}
                        className="font-semibold"
                    >
                        {currency.currencyCode} balance sheet
                    </h2>
                    <Badge
                        variant={currency.isComplete ? 'secondary' : 'outline'}
                    >
                        {currency.isComplete
                            ? 'Recorded balances'
                            : 'Partial · Known balances only'}
                    </Badge>
                </div>
                <span className="text-sm text-muted-foreground">
                    {currency.accounts.length}{' '}
                    {currency.accounts.length === 1 ? 'account' : 'accounts'}
                    {!currency.isComplete &&
                        ` · ${currency.missingAccountIds.length} missing`}
                </span>
            </header>
            <dl className="grid divide-y border-b sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                {metrics.map((metric) => (
                    <div key={metric.label} className="min-w-0 px-5 py-5">
                        <dt className="text-sm text-muted-foreground">
                            {metric.label}
                            {!currency.isComplete && <span> (partial)</span>}
                        </dt>
                        <dd className="mt-2 text-xl font-medium break-all tabular-nums md:text-2xl">
                            {hasKnownBalances
                                ? formatDecimal(
                                      metric.value,
                                      currency.currencyCode,
                                  )
                                : '—'}
                        </dd>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {hasKnownBalances
                                ? currency.currencyCode
                                : 'Awaiting balance observations'}
                        </p>
                    </div>
                ))}
            </dl>
            <div className="grid gap-6 border-b px-5 py-5 md:grid-cols-2 md:gap-10">
                <TypeAllocation
                    title={
                        currency.isComplete
                            ? 'Assets by account type'
                            : 'Known assets by account type'
                    }
                    amounts={currency.assetsByAccountType}
                    currency={currency}
                />
                <TypeAllocation
                    title={
                        currency.isComplete
                            ? 'Liabilities by account type'
                            : 'Known liabilities by account type'
                    }
                    amounts={currency.liabilitiesByAccountType}
                    currency={currency}
                />
            </div>
            <div className="grid gap-6 px-5 py-5">
                <div className="grid gap-1">
                    <h3 className="font-medium">Accounts by institution</h3>
                    <p className="text-sm text-muted-foreground">
                        Balances keep their asset or liability sign. The
                        contribution column shows their effect on net worth.
                    </p>
                </div>
                {[...institutions.entries()].map(([id, institution]) => (
                    <section
                        key={id ?? 'none'}
                        aria-label={institution.name}
                        className="min-w-0"
                    >
                        <h4 className="mb-3 flex items-center gap-2 text-sm font-medium">
                            <Building2 className="size-4 shrink-0 text-muted-foreground" />
                            {institution.name}
                        </h4>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    {currency.currencyCode} accounts at{' '}
                                    {institution.name}
                                </caption>
                                <thead className="border-y bg-muted/40 text-muted-foreground">
                                    <tr>
                                        {[
                                            'Account',
                                            'Account type',
                                            'Observation date',
                                            'Balance',
                                            'Net-worth contribution',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className="px-3 py-3 font-medium whitespace-nowrap"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {institution.accounts.map((account) => (
                                        <tr key={account.accountId}>
                                            <td className="min-w-44 px-3 py-4">
                                                <Link
                                                    href={show([
                                                        team.slug,
                                                        account.accountId,
                                                    ])}
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {account.accountName}
                                                </Link>
                                                {account.archivedAt && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="mt-1 block w-fit"
                                                    >
                                                        Archived
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-3 py-4 whitespace-nowrap">
                                                <span>{account.typeLabel}</span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {account.isLiability
                                                        ? 'Liability'
                                                        : 'Asset'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-4 whitespace-nowrap">
                                                {account.balanceAsOfDate ? (
                                                    <time
                                                        dateTime={
                                                            account.balanceAsOfDate
                                                        }
                                                        title={
                                                            account.balanceRecordedAt
                                                                ? `Recorded ${account.balanceRecordedAt}`
                                                                : undefined
                                                        }
                                                    >
                                                        {
                                                            account.balanceAsOfDate
                                                        }
                                                    </time>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        No valid observation
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-4 text-right whitespace-nowrap tabular-nums">
                                                {account.balanceAmount ===
                                                null ? (
                                                    <div className="grid justify-items-end gap-1">
                                                        <Badge variant="outline">
                                                            Missing balance
                                                        </Badge>
                                                        <Link
                                                            href={show([
                                                                team.slug,
                                                                account.accountId,
                                                            ])}
                                                            className="text-xs underline underline-offset-4"
                                                        >
                                                            {canManageAccounts &&
                                                            !account.archivedAt
                                                                ? 'Record balance'
                                                                : 'View account'}
                                                        </Link>
                                                    </div>
                                                ) : (
                                                    formatDecimal(
                                                        account.balanceAmount,
                                                        currency.currencyCode,
                                                    )
                                                )}
                                            </td>
                                            <td className="px-3 py-4 text-right whitespace-nowrap tabular-nums">
                                                {account.netWorthContribution ===
                                                null ? (
                                                    <span className="text-muted-foreground">
                                                        Not included
                                                    </span>
                                                ) : (
                                                    formatDecimal(
                                                        account.netWorthContribution,
                                                        currency.currencyCode,
                                                    )
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </section>
    );
}
