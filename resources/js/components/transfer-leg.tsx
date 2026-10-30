import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { formatDecimal } from '@/lib/format-decimal';
import { show as accountShow } from '@/routes/accounts';
import { show as transactionShow } from '@/routes/transactions';
import type { FinancialTeam } from '@/types/finance';
import { kindLabel } from '@/types/transactions';
import type { TransferAccount, TransferLeg } from '@/types/transfers';

/** Presentation of a stored magnitude; no currency conversion or accounting arithmetic. */
export function bookedAmount(leg: TransferLeg): string {
    return formatDecimal(
        leg.amount.startsWith('-') ? leg.amount.slice(1) : leg.amount,
        leg.currency_code,
    );
}
export function accountEffect(
    account: TransferAccount,
    source: boolean,
): string {
    if (account.isLiability) {
        return source
            ? 'Amount owed increases.'
            : 'Amount owed decreases (debt payment).';
    }
    return source ? 'Value owned decreases.' : 'Value owned increases.';
}
export default function TransferLegPanel({
    leg,
    source,
    team,
}: {
    leg: TransferLeg;
    source: boolean;
    team: FinancialTeam;
}) {
    return (
        <section className="grid gap-4 rounded-md border bg-card p-6">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <h2 className="font-medium">
                    {source ? 'Source' : 'Destination'}
                </h2>
                <Badge variant="secondary">{kindLabel(leg.status)}</Badge>
            </div>
            <Link
                className="font-medium underline underline-offset-4"
                href={accountShow([team.slug, leg.account.id])}
            >
                {leg.account.name}
            </Link>
            <p className="text-sm text-muted-foreground">
                {leg.account.typeLabel} ·{' '}
                {leg.account.institution?.name || 'Institution not recorded'}
                {leg.account.archived_at ? ' · Archived' : ''}
            </p>
            <p className="text-2xl break-all tabular-nums">
                {bookedAmount(leg)} {leg.currency_code}
            </p>
            <p className="text-sm text-muted-foreground">
                {accountEffect(leg.account, source)}
            </p>
            <dl className="grid gap-3 text-sm">
                <div>
                    <dt className="text-muted-foreground">
                        Recorded account movement
                    </dt>
                    <dd className="break-all tabular-nums">
                        {formatDecimal(leg.amount, leg.currency_code)}{' '}
                        {leg.currency_code}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Transaction date</dt>
                    <dd>{leg.transaction_date}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Posting date</dt>
                    <dd>{leg.posted_date || 'Not posted'}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        Income / expense analytics
                    </dt>
                    <dd>
                        {leg.excluded_from_analytics
                            ? 'Excluded'
                            : 'Not explicitly excluded'}
                    </dd>
                </div>
            </dl>
            <Link
                className="text-sm underline underline-offset-4"
                href={transactionShow([team.slug, leg.id])}
            >
                View transaction and audit history
            </Link>
        </section>
    );
}
