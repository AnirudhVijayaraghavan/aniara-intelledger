import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ReverseTransactionDialog from '@/components/reverse-transaction-dialog';
import {
    TransactionFinancialFields,
    TransactionMetadataFields,
    TransactionPagination,
} from '@/components/transaction-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDecimal } from '@/lib/format-decimal';
import { show as accountShow } from '@/routes/accounts';
import { cancel, classify, index, post, show } from '@/routes/transactions';
import { kindLabel } from '@/types/transactions';
import type {
    ClassificationInput,
    Paginated,
    Revision,
    Transaction,
    TransactionOptions,
} from '@/types/transactions';

type Props = TransactionOptions & {
    transaction: Transaction;
    revisions: Paginated<Revision>;
    canManage: boolean;
    canReverse: boolean;
    reversalId: number | null;
};
const recordedTime = (value: string) =>
    value.replace('T', ' ').replace('Z', ' UTC');

function Management({
    transaction,
    team,
    categories,
    payees,
    today,
    onAccepted,
}: TransactionOptions & {
    transaction: Transaction;
    onAccepted: (revision: number) => void;
}) {
    const [openedTransaction] = useState(transaction);
    const [cancelOpen, setCancelOpen] = useState(false);
    const posting = useForm({
        expected_revision: transaction.expected_revision,
        amount: transaction.amount,
        transaction_date: transaction.transaction_date,
        posted_date: transaction.posted_date || today,
        reason: '',
    });
    const classification = useForm<
        ClassificationInput & { expected_revision: number }
    >({
        expected_revision: transaction.expected_revision,
        kind: transaction.kind,
        category_id:
            transaction.category_id === null
                ? ''
                : String(transaction.category_id),
        payee_id:
            transaction.payee_id === null ? '' : String(transaction.payee_id),
        description: transaction.description || '',
        excluded_from_analytics: transaction.excluded_from_analytics,
        reason: '',
    });
    const cancellation = useForm({
        expected_revision: transaction.expected_revision,
        reason: '',
    });
    const submitPost = (event: FormEvent) => {
        event.preventDefault();
        posting.post(post.url([team.slug, transaction.id]), {
            onSuccess: (page) =>
                onAccepted(
                    (page.props.transaction as Transaction).expected_revision,
                ),
        });
    };
    const submitClassification = (event: FormEvent) => {
        event.preventDefault();
        classification.transform((data) => ({
            ...data,
            category_id: data.category_id || null,
            payee_id: data.payee_id || null,
        }));
        classification.patch(classify.url([team.slug, transaction.id]), {
            onSuccess: (page) =>
                onAccepted(
                    (page.props.transaction as Transaction).expected_revision,
                ),
        });
    };
    const submitCancel = (event: FormEvent) => {
        event.preventDefault();
        cancellation.post(cancel.url([team.slug, transaction.id]), {
            onSuccess: (page) =>
                onAccepted(
                    (page.props.transaction as Transaction).expected_revision,
                ),
        });
    };
    return (
        <>
            {openedTransaction.status === 'pending' && (
                <section className="grid gap-4 rounded-md border bg-card p-6">
                    <h2 className="font-medium">Post pending transaction</h2>
                    <p className="text-sm text-muted-foreground">
                        Confirm the final account movement and posting date.
                        Posting preserves the pending observation in revision
                        history.
                    </p>
                    <form
                        onSubmit={submitPost}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <TransactionFinancialFields
                            prefix="post"
                            data={posting.data}
                            onChange={(patch) =>
                                posting.setData({ ...posting.data, ...patch })
                            }
                            errors={posting.errors}
                            currency={openedTransaction.currency_code}
                            isLiability={openedTransaction.account.isLiability}
                            includePosted
                        />
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="post-reason">
                                Reason / audit note (optional)
                            </Label>
                            <Input
                                id="post-reason"
                                value={posting.data.reason}
                                maxLength={2000}
                                onChange={(event) =>
                                    posting.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={posting.errors.reason} />
                        </div>
                        <InputError
                            message={posting.errors.expected_revision}
                        />
                        <div className="flex flex-wrap gap-2 sm:col-span-2">
                            <Button disabled={posting.processing}>
                                Post transaction
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCancelOpen(true)}
                            >
                                Cancel pending transaction
                            </Button>
                        </div>
                    </form>
                </section>
            )}
            <section className="grid gap-4 rounded-md border bg-card p-6">
                <h2 className="font-medium">Classification</h2>
                <p className="text-sm text-muted-foreground">
                    Classification changes preserve the amount, currency, dates,
                    and account.
                </p>
                <form
                    onSubmit={submitClassification}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    <TransactionMetadataFields
                        team={team}
                        prefix="classify"
                        data={classification.data}
                        onChange={(patch) =>
                            classification.setData((current) => ({
                                ...current,
                                ...patch,
                            }))
                        }
                        errors={classification.errors}
                        categories={categories}
                        payees={payees}
                    />
                    <InputError
                        message={classification.errors.expected_revision}
                    />
                    <div className="sm:col-span-2">
                        <Button disabled={classification.processing}>
                            Save classification
                        </Button>
                    </div>
                </form>
            </section>
            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel pending transaction?</DialogTitle>
                        <DialogDescription>
                            The transaction and its audit history will be
                            retained. Posted activity cannot be cancelled.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCancel} className="grid gap-4">
                        <Label htmlFor="cancel-reason">Reason (optional)</Label>
                        <Input
                            id="cancel-reason"
                            value={cancellation.data.reason}
                            maxLength={2000}
                            onChange={(event) =>
                                cancellation.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={cancellation.errors.reason} />
                        <InputError
                            message={cancellation.errors.expected_revision}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCancelOpen(false)}
                            >
                                Keep transaction
                            </Button>
                            <Button disabled={cancellation.processing}>
                                Confirm cancellation
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function TransactionDetails({
    team,
    transaction,
    revisions,
    categories,
    payees,
    today,
    canManage,
    canReverse,
    reversalId,
}: Props) {
    const [formRevision, setFormRevision] = useState(
        transaction.expected_revision,
    );
    const errors = usePage().props.errors;
    const linkedTransactionId =
        reversalId ?? transaction.reverses_transaction_id;
    const rows = [
        ['Transaction date', transaction.transaction_date],
        ['Posting date', transaction.posted_date || 'Not posted'],
        ['Recorded at', recordedTime(transaction.recorded_at)],
        ['Category', transaction.category?.name || 'Uncategorized'],
        ['Payee', transaction.payee?.name || 'Not recorded'],
        [
            'Spending analytics',
            transaction.excluded_from_analytics
                ? 'Excluded'
                : 'Not explicitly excluded',
        ],
    ];
    return (
        <>
            <Head
                title={
                    transaction.description || `Transaction #${transaction.id}`
                }
            />
            <div className="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={
                            transaction.description ||
                            `Transaction #${transaction.id}`
                        }
                        description={team.name}
                    />
                    <div className="flex flex-wrap gap-2">
                        {canReverse && (
                            <ReverseTransactionDialog
                                key={`${transaction.id}:${formRevision}`}
                                transaction={transaction}
                                team={team}
                                today={today}
                            />
                        )}
                        <Badge variant="secondary">
                            {kindLabel(transaction.kind)}
                        </Badge>
                        <Badge variant="secondary">
                            {kindLabel(transaction.status)}
                        </Badge>
                        <Button variant="outline" asChild>
                            <Link href={index(team.slug)}>
                                All transactions
                            </Link>
                        </Button>
                    </div>
                </div>
                {(errors.expected_revision ||
                    errors.status ||
                    errors.reversal) && (
                    <div
                        role="alert"
                        className="grid gap-3 rounded-md border border-destructive/50 bg-card p-4"
                    >
                        <InputError
                            message={
                                errors.expected_revision ||
                                errors.status ||
                                errors.reversal
                            }
                        />
                        <p className="text-sm">
                            Your draft has not been submitted again. Reload the
                            accepted transaction before making another change.
                        </p>
                        <Button variant="outline" asChild>
                            <Link
                                href={show([team.slug, transaction.id])}
                                preserveState={false}
                            >
                                Reload latest transaction
                            </Link>
                        </Button>
                    </div>
                )}
                <section className="grid gap-4 rounded-md border bg-card p-6">
                    <h2 className="font-medium">Native account movement</h2>
                    <p className="text-2xl break-all tabular-nums">
                        {formatDecimal(
                            transaction.amount,
                            transaction.currency_code,
                        )}{' '}
                        {transaction.currency_code}
                    </p>
                    <Link
                        className="underline underline-offset-4"
                        href={accountShow([team.slug, transaction.account.id])}
                    >
                        {transaction.account.name} ·{' '}
                        {transaction.account.typeLabel}
                        {transaction.account.archived_at ? ' · Archived' : ''}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        {transaction.account.isLiability
                            ? 'Positive increases the amount owed; negative reduces debt.'
                            : 'Positive increases value owned; negative reduces it.'}{' '}
                        Kind determines classification independently of the
                        sign. Balance observations are unchanged.
                    </p>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        {rows.map(([label, value]) => (
                            <div key={label} className="grid gap-1">
                                <dt className="text-sm text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="break-words">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </section>
                {linkedTransactionId !== null && (
                    <section
                        className="grid gap-2 rounded-md border bg-card p-4"
                        aria-label="Reversal status"
                    >
                        <h2 className="font-medium">
                            {reversalId !== null
                                ? 'Transaction reversed'
                                : 'Reversal transaction'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Both entries are retained with their audit history
                            and are read-only. Recorded account balances are
                            unchanged.
                        </p>
                        <Link
                            className="underline underline-offset-4"
                            href={show([team.slug, linkedTransactionId])}
                        >
                            {reversalId !== null
                                ? `View reversal #${reversalId}`
                                : `View original transaction #${transaction.reverses_transaction_id}`}
                        </Link>
                    </section>
                )}
                {canManage && (
                    <Management
                        key={`${transaction.id}:${formRevision}`}
                        transaction={transaction}
                        team={team}
                        categories={categories}
                        payees={payees}
                        today={today}
                        onAccepted={setFormRevision}
                    />
                )}
                {!canManage &&
                    reversalId === null &&
                    transaction.reverses_transaction_id === null && (
                        <p className="text-sm text-muted-foreground">
                            {transaction.kind === 'transfer'
                                ? 'Transfer entries are reserved for the dedicated transfer workflow.'
                                : transaction.account.archived_at
                                  ? 'Archived accounts retain transaction history and cannot be changed.'
                                  : transaction.status === 'cancelled'
                                    ? 'Cancelled transactions are retained for reference.'
                                    : 'Household members can view transactions. Owners and admins manage them.'}
                        </p>
                    )}
                <section
                    className="grid gap-4"
                    aria-labelledby="revision-history"
                >
                    <h2 id="revision-history" className="font-medium">
                        Revision history
                    </h2>
                    {revisions.data.map((revision) => (
                        <details
                            key={revision.id}
                            className="rounded-md border bg-card p-4"
                        >
                            <summary className="cursor-pointer font-medium">
                                Revision {revision.revision_number} ·{' '}
                                {kindLabel(revision.operation)} ·{' '}
                                {revision.actor?.name || 'System / former user'}{' '}
                                · {recordedTime(revision.recorded_at)}
                            </summary>
                            {revision.reason && (
                                <p className="mt-3 text-sm">
                                    {revision.reason}
                                </p>
                            )}
                            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                {[
                                    [
                                        'Account movement',
                                        `${formatDecimal(String(revision.snapshot.amount), String(revision.snapshot.currency_code))} ${revision.snapshot.currency_code}`,
                                    ],
                                    [
                                        'Kind / status',
                                        `${revision.snapshot.kind} / ${revision.snapshot.status}`,
                                    ],
                                    [
                                        'Transaction date',
                                        revision.snapshot.transaction_date,
                                    ],
                                    [
                                        'Posting date',
                                        revision.snapshot.posted_date ||
                                            'Not posted',
                                    ],
                                    [
                                        'Description',
                                        revision.snapshot.description ||
                                            'Not recorded',
                                    ],
                                    [
                                        'Category reference',
                                        revision.snapshot.category_id
                                            ? `Category #${revision.snapshot.category_id}`
                                            : 'Uncategorized',
                                    ],
                                    [
                                        'Payee reference',
                                        revision.snapshot.payee_id
                                            ? `Payee #${revision.snapshot.payee_id}`
                                            : 'Not recorded',
                                    ],
                                    [
                                        'Analytics exclusion',
                                        revision.snapshot
                                            .excluded_from_analytics
                                            ? 'Excluded'
                                            : 'Not explicitly excluded',
                                    ],
                                    [
                                        'Original recording time',
                                        recordedTime(
                                            String(
                                                revision.snapshot.recorded_at,
                                            ),
                                        ),
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        key={String(label)}
                                        className="grid gap-1"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="break-words">
                                            {String(value)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </details>
                    ))}
                    <TransactionPagination
                        page={revisions}
                        url={(revisions_page) =>
                            show.url([team.slug, transaction.id], {
                                query: { revisions_page },
                            })
                        }
                    />
                </section>
            </div>
        </>
    );
}
