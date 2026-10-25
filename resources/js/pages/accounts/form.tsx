import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, X } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, edit, index, show, store, update } from '@/routes/accounts';
import type {
    AccountType,
    FinancialAccount,
    FinancialTeam,
} from '@/types/finance';

type Props = {
    team: FinancialTeam;
    account: FinancialAccount | null;
    hasHistory: boolean;
    types: AccountType[];
    currencies: { code: string; name: string }[];
    institutions: { id: number; name: string; archived_at: string | null }[];
    members: { id: number; name: string }[];
};

type AccountInput = {
    name: string;
    type: string;
    currency_code: string;
    financial_institution_id: number | null;
    institution: { name: string; country_code: string } | null;
    owners: { id: number | null; owner_name: string; user_id: string }[];
};

function Choice({
    id,
    label,
    value,
    options,
    onChange,
    disabled,
    error,
}: {
    id: string;
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    disabled?: boolean;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger
                    id={id}
                    aria-invalid={!!error}
                    aria-describedby={`${id}-error`}
                    className="w-full"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

export default function AccountForm({
    team,
    account,
    hasHistory,
    types,
    currencies,
    institutions,
    members,
}: Props) {
    const form = useForm<AccountInput>({
        name: account?.name ?? '',
        type: account?.type ?? 'checking',
        currency_code:
            account?.currency_code ??
            currencies.find((currency) => currency.code === 'USD')?.code ??
            currencies[0]?.code ??
            '',
        financial_institution_id: account?.financial_institution_id ?? null,
        institution: null,
        owners: account?.owners.map((owner) => ({
            id: owner.id,
            owner_name: owner.owner_name,
            user_id: owner.user_id === null ? '' : String(owner.user_id),
        })) ?? [{ id: null, owner_name: '', user_id: '' }],
    });
    const { data, setData, errors, processing } = form;
    const title = account ? 'Edit account' : 'Add account';
    const errorAt = (key: string) =>
        (errors as Record<string, string | undefined>)[key];
    const setHolder = (
        index: number,
        fields: Partial<AccountInput['owners'][number]>,
    ) => {
        setData(
            'owners',
            data.owners.map((owner, ownerIndex) =>
                ownerIndex === index ? { ...owner, ...fields } : owner,
            ),
        );
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((input) => ({
            ...input,
            owners: input.owners.map((owner) => ({
                ...owner,
                user_id: owner.user_id === '' ? null : Number(owner.user_id),
            })),
        }));
        form.submit(
            account ? update([team.slug, account.id]) : store(team.slug),
        );
    };

    return (
        <>
            <Head title={title} />
            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={title}
                    description={`Record an account for ${team.name}. All household members can view it.`}
                />
                <form onSubmit={submit} className="flex flex-col gap-8">
                    <section
                        aria-labelledby="account-details"
                        className="grid gap-5 rounded-md border bg-card p-5 sm:grid-cols-2"
                    >
                        <h2
                            id="account-details"
                            className="font-medium sm:col-span-2"
                        >
                            Account details
                        </h2>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="account-name">Account name</Label>
                            <Input
                                id="account-name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                maxLength={255}
                                required
                                autoFocus
                                aria-invalid={!!errors.name}
                                aria-describedby="account-name-error"
                            />
                            <InputError
                                id="account-name-error"
                                message={errors.name}
                            />
                        </div>
                        <Choice
                            id="account-type"
                            label="Account type"
                            value={data.type}
                            onChange={(value) => setData('type', value)}
                            options={types
                                .filter(
                                    (type) =>
                                        !hasHistory ||
                                        type.isLiability ===
                                            account?.isLiability,
                                )
                                .map((type) => ({
                                    value: type.value,
                                    label: `${type.label} · ${type.isLiability ? 'Liability' : 'Asset'}`,
                                }))}
                            error={errors.type}
                        />
                        <Choice
                            id="account-currency"
                            label="Currency"
                            value={data.currency_code}
                            onChange={(value) =>
                                setData('currency_code', value)
                            }
                            options={currencies.map((currency) => ({
                                value: currency.code,
                                label: `${currency.code} · ${currency.name}`,
                            }))}
                            disabled={hasHistory}
                            error={errors.currency_code}
                        />
                        {hasHistory && (
                            <p className="text-sm text-muted-foreground sm:col-span-2">
                                Balance history exists. Currency and
                                asset/liability classification are fixed; you
                                can change the account type within its
                                classification.
                            </p>
                        )}
                        <div className="sm:col-span-2">
                            <Choice
                                id="account-institution"
                                label="Institution"
                                value={
                                    data.institution !== null
                                        ? 'new'
                                        : data.financial_institution_id === null
                                          ? 'none'
                                          : String(
                                                data.financial_institution_id,
                                            )
                                }
                                onChange={(value) =>
                                    setData((current) => ({
                                        ...current,
                                        financial_institution_id:
                                            value === 'new' || value === 'none'
                                                ? null
                                                : Number(value),
                                        institution:
                                            value === 'new'
                                                ? { name: '', country_code: '' }
                                                : null,
                                    }))
                                }
                                options={[
                                    {
                                        value: 'none',
                                        label: 'No institution recorded',
                                    },
                                    ...institutions.map((institution) => ({
                                        value: String(institution.id),
                                        label:
                                            institution.name +
                                            (institution.archived_at
                                                ? ' (archived)'
                                                : ''),
                                    })),
                                    {
                                        value: 'new',
                                        label: 'Create a custom institution…',
                                    },
                                ]}
                                error={
                                    errors.financial_institution_id ??
                                    errors.institution
                                }
                            />
                        </div>
                        {data.institution !== null && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="institution-name">
                                        New institution name
                                    </Label>
                                    <Input
                                        id="institution-name"
                                        value={data.institution.name}
                                        onChange={(event) =>
                                            setData('institution', {
                                                ...data.institution!,
                                                name: event.target.value,
                                            })
                                        }
                                        maxLength={255}
                                        required
                                        aria-invalid={
                                            !!errorAt('institution.name')
                                        }
                                        aria-describedby="institution-name-error"
                                    />
                                    <InputError
                                        id="institution-name-error"
                                        message={errorAt('institution.name')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="institution-country">
                                        Country code (optional)
                                    </Label>
                                    <Input
                                        id="institution-country"
                                        value={data.institution.country_code}
                                        onChange={(event) =>
                                            setData('institution', {
                                                ...data.institution!,
                                                country_code:
                                                    event.target.value.toUpperCase(),
                                            })
                                        }
                                        maxLength={2}
                                        placeholder="US or IN"
                                        aria-invalid={
                                            !!errorAt(
                                                'institution.country_code',
                                            )
                                        }
                                        aria-describedby="institution-country-error"
                                    />
                                    <InputError
                                        id="institution-country-error"
                                        message={errorAt(
                                            'institution.country_code',
                                        )}
                                    />
                                </div>
                            </>
                        )}
                    </section>
                    <section
                        aria-labelledby="account-holders"
                        className="flex flex-col gap-5 rounded-md border bg-card p-5"
                    >
                        <div className="grid gap-1">
                            <h2 id="account-holders" className="font-medium">
                                Recorded account holders
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Record at least one holder. Holders may have no
                                login; linking a household member does not
                                change their permissions. Existing holder
                                records are retained.
                            </p>
                        </div>
                        <InputError message={errors.owners} />
                        {data.owners.map((owner, ownerIndex) => (
                            <div
                                key={owner.id ?? `new-${ownerIndex}`}
                                className="grid items-start gap-4 border-b pb-5 sm:grid-cols-2"
                            >
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`holder-${ownerIndex}-name`}
                                    >
                                        Holder {ownerIndex + 1} name
                                    </Label>
                                    <Input
                                        id={`holder-${ownerIndex}-name`}
                                        value={owner.owner_name}
                                        onChange={(event) =>
                                            setHolder(ownerIndex, {
                                                owner_name: event.target.value,
                                            })
                                        }
                                        maxLength={255}
                                        required
                                        aria-invalid={
                                            !!errorAt(
                                                `owners.${ownerIndex}.owner_name`,
                                            )
                                        }
                                        aria-describedby={`holder-${ownerIndex}-error`}
                                    />
                                    <InputError
                                        id={`holder-${ownerIndex}-error`}
                                        message={
                                            errorAt(
                                                `owners.${ownerIndex}.owner_name`,
                                            ) ??
                                            errorAt(`owners.${ownerIndex}.id`)
                                        }
                                    />
                                </div>
                                <Choice
                                    id={`holder-${ownerIndex}-member`}
                                    label="Linked household member (optional)"
                                    value={owner.user_id || 'none'}
                                    onChange={(value) =>
                                        setHolder(ownerIndex, {
                                            user_id:
                                                value === 'none' ? '' : value,
                                            owner_name:
                                                owner.owner_name ||
                                                members.find(
                                                    (member) =>
                                                        String(member.id) ===
                                                        value,
                                                )?.name ||
                                                '',
                                        })
                                    }
                                    options={[
                                        {
                                            value: 'none',
                                            label: 'No linked member',
                                        },
                                        ...members.map((member) => ({
                                            value: String(member.id),
                                            label: member.name,
                                        })),
                                        ...(owner.user_id &&
                                        !members.some(
                                            (member) =>
                                                String(member.id) ===
                                                owner.user_id,
                                        )
                                            ? [
                                                  {
                                                      value: owner.user_id,
                                                      label: 'Former member (recorded link)',
                                                  },
                                              ]
                                            : []),
                                    ]}
                                    error={errorAt(
                                        `owners.${ownerIndex}.user_id`,
                                    )}
                                />
                                {owner.id === null &&
                                    data.owners.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="justify-self-start"
                                            onClick={() =>
                                                setData(
                                                    'owners',
                                                    data.owners.filter(
                                                        (_, index) =>
                                                            index !==
                                                            ownerIndex,
                                                    ),
                                                )
                                            }
                                        >
                                            <X />
                                            Remove new holder
                                        </Button>
                                    )}
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            className="self-start"
                            onClick={() =>
                                setData('owners', [
                                    ...data.owners,
                                    { id: null, owner_name: '', user_id: '' },
                                ])
                            }
                        >
                            <Plus />
                            Add holder
                        </Button>
                    </section>
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={processing}>
                            <Save />
                            {processing ? 'Saving…' : 'Save account'}
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={
                                    account
                                        ? show([team.slug, account.id])
                                        : index(team.slug)
                                }
                            >
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AccountForm.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Accounts', href: index(props.team.slug) },
        ...(props.account
            ? [
                  {
                      title: props.account.name,
                      href: show([props.team.slug, props.account.id]),
                  },
              ]
            : []),
        {
            title: props.account ? 'Edit account' : 'Add account',
            href: props.account
                ? edit([props.team.slug, props.account.id])
                : create(props.team.slug),
        },
    ],
});
