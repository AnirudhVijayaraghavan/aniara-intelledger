import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { FinancialChoice } from '@/components/financial-choice';
import Heading from '@/components/heading';
import { TransactionPagination } from '@/components/transaction-fields';
import { accountEffect, bookedAmount } from '@/components/transfer-leg';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatUtcTimestamp } from '@/lib/financial-dates';
import { index as transactionsIndex } from '@/routes/transactions';
import { create, index, show } from '@/routes/transfers';
import type { FinancialTeam } from '@/types/finance';
import type { Paginated } from '@/types/transactions';
import type { Transfer, TransferAccount } from '@/types/transfers';

export default function Transfers({
    team,
    transfers,
    accounts,
    filters,
    canCreate,
}: {
    team: FinancialTeam;
    transfers: Paginated<Transfer>;
    accounts: TransferAccount[];
    filters: { financial_account_id?: string };
    canCreate: boolean;
}) {
    const form = useForm({
        financial_account_id: String(filters.financial_account_id || ''),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get(index.url(team.slug), { preserveState: true });
    };
    return (
        <>
            <Head title="Transfers" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Transfers"
                        description={`Internal movements for ${team.name}. One row per original transfer; reversals are linked as corrections.`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={transactionsIndex(team.slug)}>
                                Transactions
                            </Link>
                        </Button>
                        {canCreate && (
                            <Button asChild>
                                <Link href={create(team.slug)}>
                                    <ArrowLeftRight />
                                    Transfer Money
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="min-w-56">
                        <FinancialChoice
                            id="transfer-account-filter"
                            label="Account (either leg)"
                            value={form.data.financial_account_id}
                            emptyLabel="All accounts"
                            options={accounts.map((account) => ({
                                value: String(account.id),
                                label:
                                    account.name +
                                    (account.archived_at ? ' (archived)' : ''),
                            }))}
                            onChange={(value) =>
                                form.setData('financial_account_id', value)
                            }
                            error={form.errors.financial_account_id}
                        />
                    </div>
                    <Button disabled={form.processing}>Apply</Button>
                    <Button asChild variant="outline">
                        <Link href={index(team.slug)} preserveState={false}>
                            Clear
                        </Link>
                    </Button>
                </form>
                {!transfers.data.length ? (
                    <section className="grid gap-2 rounded-md border bg-card p-8">
                        <h2 className="font-medium">No transfers recorded</h2>
                        <p className="text-sm text-muted-foreground">
                            Record a completed household transfer or adjust the
                            account filter. Recorded balance observations remain
                            independent.
                        </p>
                    </section>
                ) : (
                    <div className="overflow-x-auto rounded-md border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    {[
                                        'Transfer',
                                        'Source · booked amount',
                                        'Destination · booked amount',
                                        'Posting dates',
                                        'Status',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            className="p-4 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {transfers.data.map((transfer) => (
                                    <tr key={transfer.id}>
                                        <td className="p-4">
                                            <Link
                                                className="font-medium underline underline-offset-4"
                                                href={show([
                                                    team.slug,
                                                    transfer.id,
                                                ])}
                                            >
                                                {transfer.source.description ||
                                                    `Transfer #${transfer.id}`}
                                            </Link>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatUtcTimestamp(
                                                    transfer.recorded_at,
                                                )}
                                            </p>
                                            {transfer.reversal && (
                                                <div className="mt-2 grid gap-1 text-xs">
                                                    <Link
                                                        className="underline underline-offset-4"
                                                        href={show([
                                                            team.slug,
                                                            transfer.reversal
                                                                .id,
                                                        ])}
                                                    >
                                                        View reversal #
                                                        {transfer.reversal.id}
                                                    </Link>
                                                    <span className="text-muted-foreground">
                                                        Recorded{' '}
                                                        {formatUtcTimestamp(
                                                            transfer.reversal
                                                                .recorded_at,
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="p-4">
                                            <p>
                                                {transfer.source.account.name}
                                            </p>
                                            <p className="mt-1 whitespace-nowrap tabular-nums">
                                                {bookedAmount(transfer.source)}{' '}
                                                {transfer.source.currency_code}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {accountEffect(
                                                    transfer.source.account,
                                                    true,
                                                )}
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            <p>
                                                {
                                                    transfer.destination.account
                                                        .name
                                                }
                                            </p>
                                            <p className="mt-1 whitespace-nowrap tabular-nums">
                                                {bookedAmount(
                                                    transfer.destination,
                                                )}{' '}
                                                {
                                                    transfer.destination
                                                        .currency_code
                                                }
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {accountEffect(
                                                    transfer.destination
                                                        .account,
                                                    false,
                                                )}
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            <p>
                                                Source:{' '}
                                                {transfer.source.posted_date ||
                                                    'Not posted'}
                                            </p>
                                            <p className="mt-1">
                                                Destination:{' '}
                                                {transfer.destination
                                                    .posted_date ||
                                                    'Not posted'}
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            <Badge variant="secondary">
                                                {transfer.reversal
                                                    ? 'Reversed'
                                                    : transfer.source.status ===
                                                            'posted' &&
                                                        transfer.destination
                                                            .status === 'posted'
                                                      ? 'Completed'
                                                      : 'Check leg status'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <TransactionPagination
                    page={transfers}
                    url={(page) =>
                        index.url(team.slug, { query: { ...filters, page } })
                    }
                />
            </div>
        </>
    );
}
