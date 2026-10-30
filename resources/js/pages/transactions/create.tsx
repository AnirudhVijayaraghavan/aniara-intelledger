import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import {
    TransactionChoice,
    TransactionFinancialFields,
    TransactionMetadataFields,
} from '@/components/transaction-fields';
import { Button } from '@/components/ui/button';
import { create as createAccount } from '@/routes/accounts';
import { index, store } from '@/routes/transactions';
import { create as createTransfer } from '@/routes/transfers';
import type {
    ClassificationInput,
    FinancialInput,
    TransactionAccount,
    TransactionOptions,
} from '@/types/transactions';

type Input = ClassificationInput &
    FinancialInput & { financial_account_id: string; status: string };
export default function Create({
    team,
    accounts,
    categories,
    payees,
    today,
}: TransactionOptions & { accounts: TransactionAccount[] }) {
    const form = useForm<Input>({
        financial_account_id: String(accounts[0]?.id || ''),
        amount: '',
        kind: 'expense',
        status: 'pending',
        transaction_date: today,
        posted_date: '',
        category_id: '',
        payee_id: '',
        description: '',
        excluded_from_analytics: false,
        reason: '',
    });
    const account = accounts.find(
        (account) => String(account.id) === form.data.financial_account_id,
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            category_id: data.category_id || null,
            payee_id: data.payee_id || null,
        }));
        form.post(store.url(team.slug));
    };
    return (
        <>
            <Head title="Record transaction" />
            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Record transaction"
                    description="Record an account movement without changing balance observations. Internal transfers use a separate workflow."
                />
                <div>
                    <Button asChild variant="outline">
                        <Link href={createTransfer(team.slug)}>
                            Transfer Money between household accounts
                        </Link>
                    </Button>
                </div>
                {!accounts.length ? (
                    <div className="grid gap-4 rounded-md border bg-card p-6">
                        <p>
                            Add an active financial account before recording
                            transactions.
                        </p>
                        <Button asChild>
                            <Link href={createAccount(team.slug)}>
                                Add account
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="grid gap-4 rounded-md border bg-card p-6 sm:grid-cols-2"
                    >
                        <TransactionChoice
                            id="create-account"
                            label="Account"
                            value={form.data.financial_account_id}
                            options={accounts.map((account) => ({
                                value: String(account.id),
                                label: `${account.name} · ${account.currency_code}`,
                            }))}
                            onChange={(value) =>
                                form.setData('financial_account_id', value)
                            }
                            error={form.errors.financial_account_id}
                        />
                        <TransactionChoice
                            id="create-status"
                            label="Status"
                            value={form.data.status}
                            options={[
                                { value: 'pending', label: 'Pending' },
                                { value: 'posted', label: 'Posted' },
                            ]}
                            onChange={(status) =>
                                form.setData({
                                    ...form.data,
                                    status,
                                    posted_date:
                                        status === 'posted'
                                            ? form.data.posted_date || today
                                            : '',
                                })
                            }
                            error={form.errors.status}
                        />
                        <TransactionFinancialFields
                            prefix="create"
                            data={form.data}
                            onChange={(patch) =>
                                form.setData((current) => ({
                                    ...current,
                                    ...patch,
                                }))
                            }
                            errors={form.errors}
                            currency={account?.currency_code || ''}
                            isLiability={account?.isLiability || false}
                            includePosted={form.data.status === 'posted'}
                        />
                        <TransactionMetadataFields
                            team={team}
                            prefix="create"
                            data={form.data}
                            onChange={(patch) =>
                                form.setData((current) => ({
                                    ...current,
                                    ...patch,
                                }))
                            }
                            errors={form.errors}
                            categories={categories}
                            payees={payees}
                        />
                        <div className="flex gap-2 sm:col-span-2">
                            <Button disabled={form.processing}>
                                <Save />
                                Record transaction
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={index(team.slug)}>
                                    Back to transactions
                                </Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
