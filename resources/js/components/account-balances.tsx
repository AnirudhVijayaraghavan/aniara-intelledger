import { Form, Link } from '@inertiajs/react';
import { CircleSlash, Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/accounts';
import {
    store,
    voidMethod as voidObservation,
} from '@/routes/accounts/balances';
import type {
    BalanceHistory,
    BalanceObservation,
    FinancialAccount,
    FinancialTeam,
} from '@/types/finance';

export type AccountBalancesProps = {
    team: FinancialTeam;
    account: FinancialAccount;
    currentBalance: BalanceObservation | null;
    balances: BalanceHistory;
    canRecordBalance: boolean;
    canVoidBalances: boolean;
    today: string;
};

function recordedTime(value: string): string {
    return value.replace('T', ' ').replace('Z', ' UTC');
}

export default function AccountBalances({
    team,
    account,
    currentBalance,
    balances,
    canRecordBalance,
    canVoidBalances,
    today,
}: AccountBalancesProps) {
    const [selected, setSelected] = useState<BalanceObservation | null>(null);

    return (
        <>
            <section
                aria-labelledby="current-balance"
                className="grid gap-2 rounded-md border bg-card p-6"
            >
                <h2
                    id="current-balance"
                    className="text-sm font-medium text-muted-foreground"
                >
                    Current recorded balance
                </h2>
                {currentBalance ? (
                    <>
                        <p className="flex flex-wrap items-baseline gap-2">
                            <span className="text-2xl font-medium break-all tabular-nums">
                                {currentBalance.amount}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {account.currency_code}
                            </span>
                        </p>
                        <p className="text-sm text-muted-foreground">
                            As of{' '}
                            <time dateTime={currentBalance.as_of_date}>
                                {currentBalance.as_of_date}
                            </time>{' '}
                            · Observation #{currentBalance.id}
                        </p>
                    </>
                ) : (
                    <p className="text-muted-foreground">
                        {balances.total === 0
                            ? 'No balance recorded yet.'
                            : 'No non-voided balance observations remain.'}
                    </p>
                )}
                <p className="text-sm text-muted-foreground">
                    {account.isLiability
                        ? 'Positive values are amounts owed; negative values are overpayments.'
                        : 'Positive values are value owned; negative values are overdrafts.'}
                </p>
            </section>

            {canRecordBalance && (
                <section
                    aria-labelledby="record-balance"
                    className="grid gap-4 rounded-md border bg-card p-6"
                >
                    <div className="grid gap-1">
                        <h2 id="record-balance" className="font-medium">
                            Record a balance
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Corrections create a new observation. Existing
                            observations stay in history.
                        </p>
                    </div>
                    <Form
                        {...store.form([team.slug, account.id])}
                        resetOnSuccess
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="balance-amount">
                                        Balance ({account.currency_code})
                                    </Label>
                                    <Input
                                        id="balance-amount"
                                        name="amount"
                                        type="text"
                                        inputMode="decimal"
                                        maxLength={64}
                                        autoComplete="off"
                                        required
                                        aria-invalid={!!errors.amount}
                                        aria-describedby="balance-amount-error"
                                    />
                                    <InputError
                                        id="balance-amount-error"
                                        message={errors.amount}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="balance-date">
                                        Balance observation date
                                    </Label>
                                    <Input
                                        id="balance-date"
                                        name="as_of_date"
                                        type="date"
                                        defaultValue={today}
                                        max={today}
                                        required
                                        aria-invalid={!!errors.as_of_date}
                                        aria-describedby="balance-date-error"
                                    />
                                    <InputError
                                        id="balance-date-error"
                                        message={errors.as_of_date}
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground sm:col-span-2">
                                    Enter up to 8 decimal places using a decimal
                                    point. Future dates are unavailable.
                                </p>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="justify-self-start sm:col-span-2"
                                >
                                    <Plus />
                                    {processing
                                        ? 'Recording…'
                                        : 'Record balance'}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            )}

            <section aria-labelledby="balance-history" className="grid gap-4">
                <div className="grid gap-1">
                    <h2 id="balance-history" className="font-medium">
                        Balance history
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Newest economic date first, then recording time and
                        observation ID. Voided observations remain visible.
                    </p>
                </div>
                {balances.data.length === 0 ? (
                    <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No balance observations have been recorded for this
                        account.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Balance observations for {account.name}, newest
                                first
                            </caption>
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    {[
                                        'As of date',
                                        `Balance (${account.currency_code})`,
                                        'Recorded at',
                                        'Status',
                                        'Actions',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="px-4 py-3 font-medium whitespace-nowrap"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {balances.data.map((balance) => (
                                    <tr
                                        key={balance.id}
                                        className={
                                            balance.voided_at
                                                ? 'bg-muted/30 text-muted-foreground'
                                                : ''
                                        }
                                    >
                                        <td className="px-4 py-4 whitespace-nowrap">
                                            <time dateTime={balance.as_of_date}>
                                                {balance.as_of_date}
                                            </time>
                                            <span className="block text-xs text-muted-foreground">
                                                #{balance.id}
                                            </span>
                                        </td>
                                        <td
                                            className={`px-4 py-4 text-right whitespace-nowrap tabular-nums ${balance.voided_at ? 'line-through' : 'font-medium'}`}
                                        >
                                            {balance.amount}
                                        </td>
                                        <td className="px-4 py-4">
                                            <time
                                                dateTime={balance.recorded_at}
                                                className="whitespace-nowrap"
                                            >
                                                {recordedTime(
                                                    balance.recorded_at,
                                                )}
                                            </time>
                                            <span className="block text-xs text-muted-foreground capitalize">
                                                {balance.source}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4">
                                            {balance.voided_at ? (
                                                <div className="grid gap-1">
                                                    <Badge
                                                        variant="secondary"
                                                        className="w-fit"
                                                    >
                                                        Voided
                                                    </Badge>
                                                    <span className="text-xs">
                                                        {balance.voided_by_name
                                                            ? `By ${balance.voided_by_name} · `
                                                            : ''}
                                                        <time
                                                            dateTime={
                                                                balance.voided_at
                                                            }
                                                        >
                                                            {recordedTime(
                                                                balance.voided_at,
                                                            )}
                                                        </time>
                                                    </span>
                                                </div>
                                            ) : (
                                                <Badge variant="outline">
                                                    Recorded
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-4">
                                            {canVoidBalances &&
                                                !balance.voided_at && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setSelected(balance)
                                                        }
                                                        aria-label={`Void observation ${balance.id}`}
                                                    >
                                                        <CircleSlash />
                                                        Void
                                                    </Button>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {balances.last_page > 1 && (
                    <nav
                        aria-label="Balance history pages"
                        className="flex items-center justify-between gap-4"
                    >
                        <Button asChild variant="outline">
                            <Link
                                href={show([team.slug, account.id], {
                                    query: {
                                        page: Math.max(
                                            1,
                                            balances.current_page - 1,
                                        ),
                                    },
                                })}
                                aria-disabled={balances.current_page === 1}
                                className={
                                    balances.current_page === 1
                                        ? 'pointer-events-none opacity-50'
                                        : ''
                                }
                            >
                                Previous
                            </Link>
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {balances.current_page} of {balances.last_page}
                        </span>
                        <Button asChild variant="outline">
                            <Link
                                href={show([team.slug, account.id], {
                                    query: {
                                        page: Math.min(
                                            balances.last_page,
                                            balances.current_page + 1,
                                        ),
                                    },
                                })}
                                aria-disabled={
                                    balances.current_page === balances.last_page
                                }
                                className={
                                    balances.current_page === balances.last_page
                                        ? 'pointer-events-none opacity-50'
                                        : ''
                                }
                            >
                                Next
                            </Link>
                        </Button>
                    </nav>
                )}
            </section>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <DialogContent>
                    {selected && (
                        <Form
                            key={selected.id}
                            {...voidObservation.form([
                                team.slug,
                                account.id,
                                selected.id,
                            ])}
                            className="space-y-6"
                            onSuccess={() => setSelected(null)}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Void observation #{selected.id}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            The balance of {selected.amount}{' '}
                                            {account.currency_code} as of{' '}
                                            {selected.as_of_date} will be
                                            excluded from the current recorded
                                            balance. Its original details will
                                            remain in history. An older
                                            observation may become current.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <input
                                        type="hidden"
                                        name="confirmation"
                                        value="1"
                                    />
                                    <InputError message={errors.confirmation} />
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                disabled={processing}
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            <CircleSlash />
                                            Void observation
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
