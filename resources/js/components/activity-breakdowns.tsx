import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { formatDecimal } from '@/lib/format-decimal';
import { show as accountShow } from '@/routes/accounts';
import type { FinancialTeam } from '@/types/finance';
import type {
    ActivityTotals,
    CategoryActivity,
    CurrencyActivity,
} from '@/types/financial-activity';
import { kindLabel } from '@/types/transactions';

export function activityCategoryLabel(row: CategoryActivity): string {
    return row.parentCategoryName
        ? `${row.parentCategoryName} / ${row.categoryName}`
        : row.categoryName;
}

function TotalsCells({
    totals,
    currency,
}: {
    totals: ActivityTotals;
    currency: string;
}) {
    return (
        <>
            <td className="p-4 text-right whitespace-nowrap tabular-nums">
                {formatDecimal(totals.recordedIncome, currency)}
            </td>
            <td className="p-4 text-right whitespace-nowrap tabular-nums">
                {formatDecimal(totals.recordedExpenses, currency)}
            </td>
            <td className="p-4 text-right whitespace-nowrap tabular-nums">
                {formatDecimal(totals.netRecordedIncome, currency)}
            </td>
            <td className="p-4 text-right tabular-nums">
                {totals.transactionCount}
            </td>
        </>
    );
}

function BreakdownTable({
    title,
    firstColumn,
    currency,
    caption,
    children,
}: {
    title: string;
    firstColumn: string;
    currency: string;
    caption: string;
    children: ReactNode;
}) {
    return (
        <section className="grid min-w-0 gap-3">
            <h2 className="font-medium">
                {title} · {currency}
            </h2>
            <p className="text-sm text-muted-foreground">{caption}</p>
            <div className="overflow-x-auto rounded-md border bg-card">
                <table className="w-full min-w-[42rem] text-sm">
                    <caption className="sr-only">
                        {title}, exact recorded amounts in {currency}
                    </caption>
                    <thead className="border-b text-muted-foreground">
                        <tr>
                            {[
                                firstColumn,
                                'Recorded income',
                                'Recorded expenses',
                                'Net recorded activity',
                                'Included transactions',
                            ].map((heading, index) => (
                                <th
                                    key={heading}
                                    scope="col"
                                    className={`p-4 font-medium ${index === 0 ? 'text-left' : 'text-right'}`}
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">{children}</tbody>
                </table>
            </div>
        </section>
    );
}

export default function ActivityBreakdowns({
    activity,
    team,
}: {
    activity: CurrencyActivity;
    team: FinancialTeam;
}) {
    const currency = activity.currencyCode;
    return (
        <div className="grid min-w-0 gap-6">
            <BreakdownTable
                title="Category breakdown"
                firstColumn="Direct category"
                currency={currency}
                caption="Each transaction belongs to one direct category. Child activity is not added to parent buckets; uncategorized entries remain visible."
            >
                {activity.byCategory.map((row) => (
                    <tr key={row.categoryId ?? 'uncategorized'}>
                        <th scope="row" className="p-4 text-left font-normal">
                            <span className="break-words">
                                {activityCategoryLabel(row)}
                            </span>{' '}
                            {row.archivedAt && (
                                <Badge variant="secondary">Archived</Badge>
                            )}
                        </th>
                        <TotalsCells totals={row.totals} currency={currency} />
                    </tr>
                ))}
            </BreakdownTable>
            <BreakdownTable
                title="Account breakdown"
                firstColumn="Financial account"
                currency={currency}
                caption="Income, spending, and net activity use each account's economic signs. Credit purchases are included as spending; internal transfers are excluded."
            >
                {activity.byAccount.map((row) => (
                    <tr key={row.accountId}>
                        <th scope="row" className="p-4 text-left font-normal">
                            <Link
                                className="underline underline-offset-4"
                                href={accountShow([team.slug, row.accountId])}
                            >
                                {row.accountName}
                            </Link>{' '}
                            {row.archivedAt && (
                                <Badge variant="secondary">Archived</Badge>
                            )}
                            <p className="mt-1 text-xs text-muted-foreground">
                                {kindLabel(
                                    row.accountType.replaceAll('_', ' '),
                                )}
                            </p>
                        </th>
                        <TotalsCells totals={row.totals} currency={currency} />
                    </tr>
                ))}
            </BreakdownTable>
            <BreakdownTable
                title="Monthly trends"
                firstColumn="Posting month"
                currency={currency}
                caption="Partial first and last months respect the selected dates. Zero-count months mean no qualifying records were found, not proof of zero household activity."
            >
                {activity.monthly.map((row) => (
                    <tr key={row.month}>
                        <th scope="row" className="p-4 text-left font-normal">
                            <time dateTime={row.month}>{row.month}</time>
                            {row.totals.transactionCount === 0 && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    No included records · coverage unknown
                                </p>
                            )}
                        </th>
                        <TotalsCells totals={row.totals} currency={currency} />
                    </tr>
                ))}
            </BreakdownTable>
        </div>
    );
}
