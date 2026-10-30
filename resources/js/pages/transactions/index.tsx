import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftRight, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    TransactionChoice,
    TransactionPagination,
} from '@/components/transaction-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDecimal } from '@/lib/format-decimal';
import { show as accountShow } from '@/routes/accounts';
import { index as categoriesIndex } from '@/routes/categories';
import { index as payeesIndex } from '@/routes/payees';
import { create, index, show } from '@/routes/transactions';
import {
    create as createTransfer,
    index as transfersIndex,
} from '@/routes/transfers';
import type { FinancialTeam } from '@/types/finance';
import { kindLabel, manualKinds } from '@/types/transactions';
import type {
    Paginated,
    Transaction,
    TransactionAccount,
    TransactionCategory,
} from '@/types/transactions';

type Filters = {
    financial_account_id?: string;
    category_id?: string;
    status?: string;
    kind?: string;
    date_from?: string;
    date_to?: string;
};
export default function Transactions({
    team,
    transactions,
    filters,
    accounts,
    categories,
    canCreate,
    canCreateTransfer,
}: {
    team: FinancialTeam;
    transactions: Paginated<Transaction>;
    filters: Filters;
    accounts: TransactionAccount[];
    categories: TransactionCategory[];
    canCreate: boolean;
    canCreateTransfer: boolean;
}) {
    const form = useForm({
        financial_account_id: String(filters.financial_account_id || ''),
        category_id: String(filters.category_id || ''),
        status: filters.status || '',
        kind: filters.kind || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get(index.url(team.slug), {
            preserveState: true,
            preserveScroll: true,
        });
    };
    return (
        <>
            <Head title="Transactions" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Transactions"
                        description={`Account movements recorded for ${team.name}. Balance observations remain independent.`}
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={create(team.slug)}>
                                <Plus />
                                Record transaction
                            </Link>
                        </Button>
                    )}
                    {canCreateTransfer && (
                        <Button asChild variant="outline">
                            <Link href={createTransfer(team.slug)}>
                                <ArrowLeftRight />
                                Transfer Money
                            </Link>
                        </Button>
                    )}
                </div>
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-md border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <TransactionChoice
                        id="filter-account"
                        label="Account"
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
                    <TransactionChoice
                        id="filter-status"
                        label="Status"
                        value={form.data.status}
                        emptyLabel="All statuses"
                        options={['pending', 'posted', 'cancelled'].map(
                            (value) => ({ value, label: kindLabel(value) }),
                        )}
                        onChange={(value) => form.setData('status', value)}
                        error={form.errors.status}
                    />
                    <TransactionChoice
                        id="filter-kind"
                        label="Kind"
                        value={form.data.kind}
                        emptyLabel="All kinds"
                        options={[...manualKinds, 'transfer'].map((value) => ({
                            value,
                            label: kindLabel(value),
                        }))}
                        onChange={(value) => form.setData('kind', value)}
                        error={form.errors.kind}
                    />
                    <TransactionChoice
                        id="filter-category"
                        label="Category"
                        value={form.data.category_id}
                        emptyLabel="All categories"
                        options={categories.map((category) => ({
                            value: String(category.id),
                            label: category.name,
                        }))}
                        onChange={(value) => form.setData('category_id', value)}
                        error={form.errors.category_id}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="date-from">Transaction date from</Label>
                        <Input
                            id="date-from"
                            type="date"
                            value={form.data.date_from}
                            onChange={(event) =>
                                form.setData('date_from', event.target.value)
                            }
                        />
                        <InputError message={form.errors.date_from} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date-to">Transaction date to</Label>
                        <Input
                            id="date-to"
                            type="date"
                            value={form.data.date_to}
                            onChange={(event) =>
                                form.setData('date_to', event.target.value)
                            }
                        />
                        <InputError message={form.errors.date_to} />
                    </div>
                    <div className="flex items-end gap-2">
                        <Button disabled={form.processing}>
                            Apply filters
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index(team.slug)} preserveState={false}>
                                Clear
                            </Link>
                        </Button>
                    </div>
                </form>
                {transactions.data.length ? (
                    <div className="overflow-x-auto rounded-md border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    {[
                                        'Date / description',
                                        'Account',
                                        'Kind / status',
                                        'Category / payee',
                                        'Native account movement',
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
                                {transactions.data.map((transaction) => (
                                    <tr key={transaction.id}>
                                        <td className="p-4">
                                            <Link
                                                className="font-medium underline underline-offset-4"
                                                href={show([
                                                    team.slug,
                                                    transaction.id,
                                                ])}
                                            >
                                                {transaction.description ||
                                                    `Transaction #${transaction.id}`}
                                            </Link>
                                            <p className="mt-1 text-muted-foreground">
                                                {transaction.transaction_date}
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            <Link
                                                className="underline underline-offset-4"
                                                href={accountShow([
                                                    team.slug,
                                                    transaction.account.id,
                                                ])}
                                            >
                                                {transaction.account.name}
                                            </Link>
                                            <p className="mt-1 text-muted-foreground">
                                                {transaction.account.typeLabel}
                                                {transaction.account.archived_at
                                                    ? ' · Archived'
                                                    : ''}
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            <p>{kindLabel(transaction.kind)}</p>
                                            <Badge
                                                variant="secondary"
                                                className="mt-1"
                                            >
                                                {kindLabel(transaction.status)}
                                            </Badge>
                                            {transaction.excluded_from_analytics && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Analytics excluded
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-4">
                                            <p>
                                                {transaction.category?.name ||
                                                    'Uncategorized'}
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                {transaction.payee?.name ||
                                                    'Payee not recorded'}
                                            </p>
                                        </td>
                                        <td className="p-4 text-right whitespace-nowrap tabular-nums">
                                            {formatDecimal(
                                                transaction.amount,
                                                transaction.currency_code,
                                            )}{' '}
                                            {transaction.currency_code}
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {transaction.account.isLiability
                                                    ? 'Change in amount owed'
                                                    : 'Change in value owned'}
                                            </p>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="grid gap-2 rounded-md border bg-card p-8">
                        <h2 className="font-medium">No transactions found</h2>
                        <p className="text-sm text-muted-foreground">
                            Record a manual transaction or adjust your filters.
                            Transactions do not update recorded balances.
                        </p>
                    </div>
                )}
                <TransactionPagination
                    page={transactions}
                    url={(page) =>
                        index.url(team.slug, { query: { ...filters, page } })
                    }
                />
            </div>
            <nav
                aria-label="Transaction references"
                className="flex flex-wrap gap-2"
            >
                <Button asChild variant="outline">
                    <Link href={transfersIndex(team.slug)}>
                        Transfer history
                    </Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href={categoriesIndex(team.slug)}>Categories</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href={payeesIndex(team.slug)}>Payees</Link>
                </Button>
            </nav>
        </>
    );
}
