import { Link } from '@inertiajs/react';
import { AlertCircle, ChartNoAxesCombined, Plus, Wallet } from 'lucide-react';
import CurrencyOverview from '@/components/currency-overview';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/accounts';
import type { FinancialTeam, NetWorthOverview } from '@/types/finance';

type Props = {
    compact?: boolean;
    overview?: NetWorthOverview;
    team?: FinancialTeam;
    canManageAccounts?: boolean;
};

export default function LedgerPreview({
    compact = false,
    overview,
    team,
    canManageAccounts = false,
}: Props) {
    if (!overview || !team) {
        return (
            <section
                aria-label="Financial overview introduction"
                className={cn(
                    'overflow-hidden rounded-md border border-border bg-card text-card-foreground',
                    compact && 'ledger-preview-compact shrink-0',
                )}
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                    <span className="flex items-center gap-2 text-sm">
                        <ChartNoAxesCombined className="size-4" />
                        Financial overview
                    </span>
                    <Badge variant="secondary">Household balance sheet</Badge>
                </div>
                <div className="grid gap-4 p-5 sm:p-7">
                    <Wallet className="size-7 text-primary" />
                    <h3 className="text-xl">
                        A clearer picture starts with your accounts.
                    </h3>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Record your assets, liabilities, and dated balances in
                        one household ledger.
                    </p>
                    <dl className="grid gap-3 border-t pt-4 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">
                                Currencies
                            </dt>
                            <dd>USD and INR, separately</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">Balances</dt>
                            <dd>Recorded observations</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">
                                Visibility
                            </dt>
                            <dd>Your household</dd>
                        </div>
                    </dl>
                </div>
            </section>
        );
    }

    const currencies = Object.values(overview.currencies);
    const missingAccounts = currencies.flatMap((currency) =>
        currency.accounts.filter((account) => account.missingData),
    );

    if (currencies.length === 0) {
        return (
            <section
                aria-labelledby="empty-household"
                className="flex flex-col items-center gap-4 rounded-md border border-dashed bg-card px-6 py-14 text-center"
            >
                <Wallet className="size-8 text-muted-foreground" />
                <h2 id="empty-household" className="text-lg font-medium">
                    No financial accounts yet
                </h2>
                <p className="max-w-md text-sm text-muted-foreground">
                    {canManageAccounts
                        ? 'Add an account and record its balance to start your household balance sheet.'
                        : 'A household owner or administrator can add accounts and record balances.'}{' '}
                    USD and INR will be shown separately.
                </p>
                <Button
                    asChild
                    variant={canManageAccounts ? 'default' : 'outline'}
                >
                    <Link
                        href={
                            canManageAccounts
                                ? create(team.slug)
                                : index(team.slug)
                        }
                    >
                        {canManageAccounts ? <Plus /> : <Wallet />}
                        {canManageAccounts
                            ? 'Add your first account'
                            : 'View accounts'}
                    </Link>
                </Button>
            </section>
        );
    }

    return (
        <div className="grid gap-6">
            {!overview.isComplete && (
                <Alert className="rounded-md border-accent bg-accent/15">
                    <AlertCircle />
                    <AlertTitle className="line-clamp-none tracking-normal">
                        Partial financial overview
                    </AlertTitle>
                    <AlertDescription>
                        <p>
                            {missingAccounts.length}{' '}
                            {missingAccounts.length === 1
                                ? 'account is'
                                : 'accounts are'}{' '}
                            missing a valid balance observation. Totals include
                            known balances only.
                        </p>
                        <ul className="flex flex-wrap gap-x-5 gap-y-2 pt-2">
                            {missingAccounts.map((account) => (
                                <li key={account.accountId}>
                                    <Link
                                        href={show([
                                            team.slug,
                                            account.accountId,
                                        ])}
                                        className="font-medium text-foreground underline underline-offset-4"
                                    >
                                        {account.accountName}
                                    </Link>
                                    <span>
                                        {' '}
                                        ·{' '}
                                        {account.archivedAt
                                            ? 'Archived; view history'
                                            : canManageAccounts
                                              ? 'Record a balance'
                                              : 'View account'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {!canManageAccounts && (
                            <p className="pt-2">
                                A household owner or administrator can record
                                missing balances.
                            </p>
                        )}
                    </AlertDescription>
                </Alert>
            )}
            {currencies.map((currency) => (
                <CurrencyOverview
                    key={currency.currencyCode}
                    currency={currency}
                    team={team}
                    canManageAccounts={canManageAccounts}
                />
            ))}
        </div>
    );
}
