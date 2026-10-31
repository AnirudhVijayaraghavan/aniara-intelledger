import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import ActivityBreakdowns, {
    activityCategoryLabel,
} from '@/components/activity-breakdowns';
import {
    ActivityBars,
    MonthlyActivityChart,
} from '@/components/activity-charts';
import { FinancialChoice } from '@/components/financial-choice';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { hasRecordedAmount } from '@/lib/activity-chart';
import { formatDecimal } from '@/lib/format-decimal';
import { index } from '@/routes/financial-activity';
import {
    create as createTransaction,
    index as transactionsIndex,
} from '@/routes/transactions';
import type { FinancialTeam } from '@/types/finance';
import type {
    ActivityFilters,
    ActivityPreset,
    FinancialActivity,
} from '@/types/financial-activity';

type Props = {
    team: FinancialTeam;
    activity: FinancialActivity;
    filters: ActivityFilters;
    presets: ActivityPreset[];
    canCreateTransaction: boolean;
};

export default function FinancialActivityPage({
    team,
    activity,
    filters,
    presets,
    canCreateTransaction,
}: Props) {
    const form = useForm<ActivityFilters>({ ...filters });
    const selected = activity.currencies[filters.currency];
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get(index.url(team.slug), {
            preserveState: true,
            preserveScroll: true,
        });
    };
    return (
        <>
            <Head title="Spending & Income" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Spending & Income"
                        description={`${team.name} · recorded activity by posting date`}
                    />
                    <Button asChild variant="outline">
                        <Link
                            href={
                                canCreateTransaction
                                    ? createTransaction(team.slug)
                                    : transactionsIndex(team.slug)
                            }
                        >
                            {canCreateTransaction && <Plus />}
                            {canCreateTransaction
                                ? 'Record transaction'
                                : 'View transactions'}
                        </Link>
                    </Button>
                </div>
                <section
                    className="grid gap-2 rounded-md border bg-secondary/20 p-4"
                    aria-label="Recorded activity coverage"
                >
                    <h2 className="font-medium">{activity.label}</h2>
                    <p className="text-sm text-muted-foreground">
                        {activity.coverageNote}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Net recorded activity is not verified savings or
                        complete household cash flow. Currencies are shown
                        separately with no FX conversion.
                    </p>
                </section>
                <div className="grid gap-4">
                    <form
                        onSubmit={submit}
                        className="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto]"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="activity-start">Start date</Label>
                            <Input
                                id="activity-start"
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) =>
                                    form.setData(
                                        'start_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.start_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="activity-end">End date</Label>
                            <Input
                                id="activity-end"
                                type="date"
                                value={form.data.end_date}
                                onChange={(event) =>
                                    form.setData('end_date', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.end_date} />
                        </div>
                        <FinancialChoice
                            id="activity-currency"
                            label="Currency (original amounts)"
                            value={form.data.currency}
                            options={[
                                { value: 'USD', label: 'USD' },
                                { value: 'INR', label: 'INR' },
                            ]}
                            onChange={(value) => {
                                if (value === 'USD' || value === 'INR') {
                                    form.setData('currency', value);
                                }
                            }}
                            error={form.errors.currency}
                        />
                        <Button disabled={form.processing}>
                            Apply filters
                        </Button>
                    </form>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Calendar months:
                        </span>
                        {presets.map((preset) => (
                            <Button
                                asChild
                                variant={
                                    filters.start_date === preset.startDate &&
                                    filters.end_date === preset.endDate
                                        ? 'secondary'
                                        : 'outline'
                                }
                                size="sm"
                                key={preset.months}
                            >
                                <Link
                                    href={index(team.slug, {
                                        query: {
                                            start_date: preset.startDate,
                                            end_date: preset.endDate,
                                            currency: form.data.currency,
                                        },
                                    })}
                                    preserveState={false}
                                >
                                    {preset.months}{' '}
                                    {preset.months === 1 ? 'month' : 'months'}
                                </Link>
                            </Button>
                        ))}
                        <span className="text-xs text-muted-foreground">
                            Including the current month through month-end.
                        </span>
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">
                    Showing {filters.currency} ·{' '}
                    <time dateTime={activity.startDate}>
                        {activity.startDate}
                    </time>{' '}
                    through{' '}
                    <time dateTime={activity.endDate}>{activity.endDate}</time>,
                    inclusive.
                </p>
                {!selected ? (
                    <section className="grid gap-3 rounded-md border bg-card p-6">
                        <h2 className="font-medium">
                            No qualifying recorded activity in{' '}
                            {filters.currency}
                        </h2>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            No included posted income or expense transactions
                            were found for this currency and date range. This
                            does not establish zero household activity.
                            Transfers, adjustments, unclassified entries, and
                            explicitly excluded transactions do not appear here.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {canCreateTransaction && (
                                <Button asChild>
                                    <Link href={createTransaction(team.slug)}>
                                        <Plus /> Record transaction
                                    </Link>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link href={transactionsIndex(team.slug)}>
                                    Review transactions
                                </Link>
                            </Button>
                        </div>
                    </section>
                ) : (
                    <>
                        <section
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                            aria-label={`Recorded financial summary in ${selected.currencyCode}`}
                        >
                            {[
                                {
                                    label: 'Recorded income',
                                    value: `${formatDecimal(selected.totals.recordedIncome, selected.currencyCode)} ${selected.currencyCode}`,
                                },
                                {
                                    label: 'Recorded expenses',
                                    value: `${formatDecimal(selected.totals.recordedExpenses, selected.currencyCode)} ${selected.currencyCode}`,
                                },
                                {
                                    label: 'Net recorded activity',
                                    value: `${formatDecimal(selected.totals.netRecordedIncome, selected.currencyCode)} ${selected.currencyCode}`,
                                },
                                {
                                    label: 'Included transactions',
                                    value: String(
                                        selected.totals.transactionCount,
                                    ),
                                },
                            ].map((metric) => (
                                <div
                                    key={metric.label}
                                    className="grid content-start gap-2 rounded-md border bg-card p-5"
                                >
                                    <h2 className="text-sm text-muted-foreground">
                                        {metric.label}
                                    </h2>
                                    <p className="text-xl break-all tabular-nums">
                                        {metric.value}
                                    </p>
                                </div>
                            ))}
                        </section>
                        <section className="grid min-w-0 gap-4 rounded-md border bg-card p-5">
                            <h2 className="font-medium">
                                Monthly recorded income vs. expenses
                            </h2>
                            <MonthlyActivityChart
                                monthly={selected.monthly}
                                currency={selected.currencyCode}
                            />
                        </section>
                        <div className="grid min-w-0 gap-4 xl:grid-cols-3">
                            <ActivityBars
                                title="Spending by category"
                                kind="expense"
                                currency={selected.currencyCode}
                                rows={selected.byCategory
                                    .filter((row) =>
                                        hasRecordedAmount(
                                            row.totals.recordedExpenses,
                                        ),
                                    )
                                    .map((row) => ({
                                        id: String(
                                            row.categoryId ?? 'uncategorized',
                                        ),
                                        label: activityCategoryLabel(row),
                                        amount: row.totals.recordedExpenses,
                                        archived: row.archivedAt !== null,
                                    }))}
                            />
                            <ActivityBars
                                title="Income by category"
                                kind="income"
                                currency={selected.currencyCode}
                                rows={selected.byCategory
                                    .filter((row) =>
                                        hasRecordedAmount(
                                            row.totals.recordedIncome,
                                        ),
                                    )
                                    .map((row) => ({
                                        id: String(
                                            row.categoryId ?? 'uncategorized',
                                        ),
                                        label: activityCategoryLabel(row),
                                        amount: row.totals.recordedIncome,
                                        archived: row.archivedAt !== null,
                                    }))}
                            />
                            <ActivityBars
                                title="Spending by account"
                                kind="expense"
                                currency={selected.currencyCode}
                                rows={selected.byAccount
                                    .filter((row) =>
                                        hasRecordedAmount(
                                            row.totals.recordedExpenses,
                                        ),
                                    )
                                    .map((row) => ({
                                        id: String(row.accountId),
                                        label: row.accountName,
                                        amount: row.totals.recordedExpenses,
                                        archived: row.archivedAt !== null,
                                    }))}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Negative amounts retain refunds and reversal
                            adjustments. Breakdowns show the same included
                            transactions; do not add the tables together.
                        </p>
                        <ActivityBreakdowns activity={selected} team={team} />
                    </>
                )}
            </div>
        </>
    );
}

FinancialActivityPage.layout = (props: Props) => ({
    breadcrumbs: [{ title: 'Spending & Income', href: index(props.team.slug) }],
});
