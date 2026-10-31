import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { FinancialChoice } from '@/components/financial-choice';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { accountEffect } from '@/components/transfer-leg';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { utcInputToIso } from '@/lib/financial-dates';
import { create as createAccount } from '@/routes/accounts';
import { index, store } from '@/routes/transfers';
import type { FinancialTeam } from '@/types/finance';
import type { TransferAccount } from '@/types/transfers';

type InputData = {
    source_account_id: string;
    destination_account_id: string;
    source_amount: string;
    destination_amount: string;
    transaction_date: string;
    posted_date: string;
    description: string;
    reason: string;
    include_fx: boolean;
    execution_fx: {
        base_currency_code: string;
        quote_currency_code: string;
        rate: string;
        source: string;
        effective_at: string;
    };
};
const emptyFx = () => ({
    base_currency_code: '',
    quote_currency_code: '',
    rate: '',
    source: '',
    effective_at: '',
});
export default function CreateTransfer({
    team,
    accounts,
    today,
}: {
    team: FinancialTeam;
    accounts: TransferAccount[];
    today: string;
}) {
    const form = useForm<InputData>({
        source_account_id: String(accounts[0]?.id || ''),
        destination_account_id: String(accounts[1]?.id || ''),
        source_amount: '',
        destination_amount: '',
        transaction_date: today,
        posted_date: today,
        description: '',
        reason: '',
        include_fx: false,
        execution_fx: emptyFx(),
    });
    const source = accounts.find(
        (account) => String(account.id) === form.data.source_account_id,
    );
    const destination = accounts.find(
        (account) => String(account.id) === form.data.destination_account_id,
    );
    const crossCurrency =
        !!source &&
        !!destination &&
        source.currency_code !== destination.currency_code;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            source_account_id: data.source_account_id,
            destination_account_id: data.destination_account_id,
            source_amount: data.source_amount,
            destination_amount: crossCurrency
                ? data.destination_amount
                : data.source_amount,
            transaction_date: data.transaction_date,
            posted_date: data.posted_date,
            description: data.description,
            reason: data.reason,
            execution_fx:
                crossCurrency && data.include_fx
                    ? {
                          ...data.execution_fx,
                          effective_at: utcInputToIso(
                              data.execution_fx.effective_at,
                          ),
                      }
                    : null,
        }));
        form.post(store.url(team.slug));
    };
    const options = (rows: TransferAccount[]) =>
        rows.map((account) => ({
            value: String(account.id),
            label: `${account.name} · ${account.typeLabel} · ${account.currency_code}`,
        }));
    return (
        <>
            <Head title="Transfer Money" />
            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Transfer Money"
                    description={`Record a completed transfer between two accounts in ${team.name}. Enter positive booked amounts; accounting signs are assigned automatically.`}
                />
                {accounts.length < 2 ? (
                    <section className="grid gap-4 rounded-md border bg-card p-6">
                        <p>
                            A transfer needs two different active household
                            accounts.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={createAccount(team.slug)}>
                                    Add account
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={index(team.slug)}>
                                    Transfer history
                                </Link>
                            </Button>
                        </div>
                    </section>
                ) : (
                    <form
                        onSubmit={submit}
                        className="grid gap-5 rounded-md border bg-card p-6 sm:grid-cols-2"
                    >
                        <div className="grid gap-2">
                            <FinancialChoice
                                id="transfer-source"
                                label="Source account"
                                value={form.data.source_account_id}
                                options={options(accounts)}
                                onChange={(source_account_id) =>
                                    form.setData((current) => ({
                                        ...current,
                                        source_account_id,
                                        destination_account_id:
                                            current.destination_account_id ===
                                            source_account_id
                                                ? ''
                                                : current.destination_account_id,
                                        source_amount: '',
                                        destination_amount: '',
                                        include_fx: false,
                                        execution_fx: emptyFx(),
                                    }))
                                }
                                error={form.errors.source_account_id}
                            />
                            {source && (
                                <p className="text-sm text-muted-foreground">
                                    {accountEffect(source, true)}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <FinancialChoice
                                id="transfer-destination"
                                label="Destination account"
                                value={form.data.destination_account_id}
                                emptyLabel="Choose an account"
                                options={options(
                                    accounts.filter(
                                        (account) =>
                                            String(account.id) !==
                                            form.data.source_account_id,
                                    ),
                                )}
                                onChange={(destination_account_id) =>
                                    form.setData((current) => ({
                                        ...current,
                                        destination_account_id,
                                        destination_amount: '',
                                        include_fx: false,
                                        execution_fx: emptyFx(),
                                    }))
                                }
                                error={form.errors.destination_account_id}
                            />
                            {destination && (
                                <p className="text-sm text-muted-foreground">
                                    {accountEffect(destination, false)}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="transfer-source-amount">
                                Source amount (
                                {source?.currency_code || 'native currency'})
                            </Label>
                            <Input
                                id="transfer-source-amount"
                                inputMode="decimal"
                                value={form.data.source_amount}
                                onChange={(event) =>
                                    form.setData(
                                        'source_amount',
                                        event.target.value,
                                    )
                                }
                                maxLength={64}
                                required
                            />
                            <InputError message={form.errors.source_amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="transfer-destination-amount">
                                Destination amount (
                                {destination?.currency_code ||
                                    'native currency'}
                                )
                            </Label>
                            <Input
                                id="transfer-destination-amount"
                                inputMode="decimal"
                                value={
                                    crossCurrency
                                        ? form.data.destination_amount
                                        : form.data.source_amount
                                }
                                readOnly={!crossCurrency}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_amount',
                                        event.target.value,
                                    )
                                }
                                maxLength={64}
                                required
                            />
                            <InputError
                                message={form.errors.destination_amount}
                            />
                            <p className="text-sm text-muted-foreground">
                                {crossCurrency
                                    ? 'Enter the actual amount booked at the destination. No FX conversion is estimated.'
                                    : 'The amount matches the source for a same-currency transfer. The backend verifies the match.'}
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="transfer-date">
                                Transaction date
                            </Label>
                            <Input
                                id="transfer-date"
                                type="date"
                                value={form.data.transaction_date}
                                onChange={(event) =>
                                    form.setData(
                                        'transaction_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={form.errors.transaction_date}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="transfer-posted-date">
                                Posting date
                            </Label>
                            <Input
                                id="transfer-posted-date"
                                type="date"
                                value={form.data.posted_date}
                                onChange={(event) =>
                                    form.setData(
                                        'posted_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.posted_date} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="transfer-description">
                                Description (optional)
                            </Label>
                            <Input
                                id="transfer-description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                maxLength={255}
                            />
                            <InputError message={form.errors.description} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="transfer-reason">
                                Audit note (optional)
                            </Label>
                            <Input
                                id="transfer-reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                maxLength={2000}
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                        {crossCurrency && (
                            <div className="grid gap-4 border-t pt-4 sm:col-span-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="execution-fx-toggle"
                                        checked={form.data.include_fx}
                                        onCheckedChange={(checked) =>
                                            form.setData((current) => ({
                                                ...current,
                                                include_fx: checked === true,
                                                execution_fx: {
                                                    ...emptyFx(),
                                                    base_currency_code:
                                                        source.currency_code,
                                                    quote_currency_code:
                                                        destination.currency_code,
                                                },
                                            }))
                                        }
                                    />
                                    <Label htmlFor="execution-fx-toggle">
                                        Record an execution FX quote (optional)
                                    </Label>
                                </div>
                                <InputError
                                    message={form.errors.execution_fx}
                                />
                                {form.data.include_fx && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <FinancialChoice
                                            id="execution-fx-base"
                                            label="One unit of base currency"
                                            value={
                                                form.data.execution_fx
                                                    .base_currency_code
                                            }
                                            options={[
                                                source.currency_code,
                                                destination.currency_code,
                                            ].map((value) => ({
                                                value,
                                                label: value,
                                            }))}
                                            onChange={(base_currency_code) =>
                                                form.setData((current) => ({
                                                    ...current,
                                                    execution_fx: {
                                                        ...current.execution_fx,
                                                        base_currency_code,
                                                        quote_currency_code:
                                                            base_currency_code ===
                                                            source.currency_code
                                                                ? destination.currency_code
                                                                : source.currency_code,
                                                    },
                                                }))
                                            }
                                            error={
                                                form.errors[
                                                    'execution_fx.base_currency_code'
                                                ]
                                            }
                                        />
                                        <div className="grid gap-2">
                                            <Label htmlFor="execution-fx-rate">
                                                Rate in{' '}
                                                {
                                                    form.data.execution_fx
                                                        .quote_currency_code
                                                }
                                            </Label>
                                            <Input
                                                id="execution-fx-rate"
                                                inputMode="decimal"
                                                value={
                                                    form.data.execution_fx.rate
                                                }
                                                onChange={(event) =>
                                                    form.setData((current) => ({
                                                        ...current,
                                                        execution_fx: {
                                                            ...current.execution_fx,
                                                            rate: event.target
                                                                .value,
                                                        },
                                                    }))
                                                }
                                                maxLength={80}
                                                required
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        'execution_fx.rate'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="execution-fx-source">
                                                Quote source
                                            </Label>
                                            <Input
                                                id="execution-fx-source"
                                                value={
                                                    form.data.execution_fx
                                                        .source
                                                }
                                                onChange={(event) =>
                                                    form.setData((current) => ({
                                                        ...current,
                                                        execution_fx: {
                                                            ...current.execution_fx,
                                                            source: event.target
                                                                .value,
                                                        },
                                                    }))
                                                }
                                                maxLength={64}
                                                required
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        'execution_fx.source'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="execution-fx-effective">
                                                Effective time (UTC)
                                            </Label>
                                            <Input
                                                id="execution-fx-effective"
                                                type="datetime-local"
                                                step="0.000001"
                                                value={
                                                    form.data.execution_fx
                                                        .effective_at
                                                }
                                                onChange={(event) =>
                                                    form.setData((current) => ({
                                                        ...current,
                                                        execution_fx: {
                                                            ...current.execution_fx,
                                                            effective_at:
                                                                event.target
                                                                    .value,
                                                        },
                                                    }))
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        'execution_fx.effective_at'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <p className="text-sm text-muted-foreground sm:col-span-2">
                                            1{' '}
                                            {
                                                form.data.execution_fx
                                                    .base_currency_code
                                            }{' '}
                                            = rate{' '}
                                            {
                                                form.data.execution_fx
                                                    .quote_currency_code
                                            }
                                            . This provenance does not calculate
                                            or override either booked amount.
                                        </p>
                                        <InputError
                                            message={
                                                form.errors[
                                                    'execution_fx.quote_currency_code'
                                                ]
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                        <p className="text-sm text-muted-foreground sm:col-span-2">
                            Both legs will be posted together and excluded from
                            income and expense analytics. Existing balance
                            observations will not change.
                        </p>
                        <div className="flex flex-wrap gap-2 sm:col-span-2">
                            <Button
                                disabled={
                                    form.processing || !source || !destination
                                }
                            >
                                <ArrowLeftRight />
                                Record completed transfer
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={index(team.slug)}>Cancel</Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
