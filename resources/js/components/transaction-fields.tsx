import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { FinancialChoice as TransactionChoice } from '@/components/financial-choice';
import InlineReferenceCreate from '@/components/inline-reference-create';
import type { FinancialTeam } from '@/types/finance';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { kindLabel, manualKinds } from '@/types/transactions';
import type {
    ClassificationInput,
    FinancialInput,
    Payee,
    TransactionCategory,
} from '@/types/transactions';

type Errors = Record<string, string | undefined>;
export { FinancialChoice as TransactionChoice } from '@/components/financial-choice';
export function TransactionFinancialFields({
    prefix,
    data,
    onChange,
    errors,
    currency,
    isLiability,
    includePosted,
}: {
    prefix: string;
    data: FinancialInput;
    onChange: (patch: Partial<FinancialInput>) => void;
    errors: Errors;
    currency: string;
    isLiability: boolean;
    includePosted: boolean;
}) {
    return (
        <>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={prefix + '-amount'}>
                    Account movement ({currency})
                </Label>
                <Input
                    id={prefix + '-amount'}
                    value={data.amount}
                    onChange={(event) =>
                        onChange({ amount: event.target.value })
                    }
                    inputMode="decimal"
                    required
                    maxLength={64}
                    aria-invalid={!!errors.amount}
                    aria-describedby={
                        errors.amount
                            ? `${prefix}-amount-help ${prefix}-amount-error`
                            : `${prefix}-amount-help`
                    }
                />
                <p
                    id={prefix + '-amount-help'}
                    className="text-sm text-muted-foreground"
                >
                    {isLiability
                        ? 'Positive increases the amount owed; negative reduces debt. A card purchase is an expense, not income.'
                        : 'Positive increases value owned; negative reduces it.'}
                </p>
                <InputError
                    id={prefix + '-amount-error'}
                    message={errors.amount}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={prefix + '-date'}>Transaction date</Label>
                <Input
                    id={prefix + '-date'}
                    type="date"
                    value={data.transaction_date}
                    aria-invalid={!!errors.transaction_date}
                    aria-describedby={
                        errors.transaction_date
                            ? prefix + '-date-error'
                            : undefined
                    }
                    onChange={(event) =>
                        onChange({ transaction_date: event.target.value })
                    }
                    required
                />
                <InputError
                    id={prefix + '-date-error'}
                    message={errors.transaction_date}
                />
            </div>
            {includePosted && (
                <div className="grid gap-2">
                    <Label htmlFor={prefix + '-posted'}>Posting date</Label>
                    <Input
                        id={prefix + '-posted'}
                        type="date"
                        value={data.posted_date}
                        aria-invalid={!!errors.posted_date}
                        aria-describedby={
                            errors.posted_date
                                ? prefix + '-posted-error'
                                : undefined
                        }
                        onChange={(event) =>
                            onChange({ posted_date: event.target.value })
                        }
                        required
                    />
                    <InputError
                        id={prefix + '-posted-error'}
                        message={errors.posted_date}
                    />
                </div>
            )}
        </>
    );
}

export function TransactionMetadataFields({
    prefix,
    data,
    onChange,
    errors,
    categories,
    payees,
    team,
}: {
    prefix: string;
    data: ClassificationInput;
    onChange: (patch: Partial<ClassificationInput>) => void;
    errors: Errors;
    categories: TransactionCategory[];
    payees: Payee[];
    team: FinancialTeam;
}) {
    const [addedCategories, setAddedCategories] = useState<
        TransactionCategory[]
    >([]);
    const [addedPayees, setAddedPayees] = useState<Payee[]>([]);
    const availableCategories = [
        ...new Map(
            [...categories, ...addedCategories].map((category) => [
                category.id,
                category,
            ]),
        ).values(),
    ];
    const availablePayees = [
        ...new Map(
            [...payees, ...addedPayees].map((payee) => [payee.id, payee]),
        ).values(),
    ];
    return (
        <>
            <TransactionChoice
                id={prefix + '-kind'}
                label="Kind"
                value={data.kind}
                options={manualKinds.map((value) => ({
                    value,
                    label: kindLabel(value),
                }))}
                onChange={(kind) => onChange({ kind, category_id: '' })}
                error={errors.kind}
            />
            <TransactionChoice
                id={prefix + '-category'}
                label="Category (optional)"
                value={data.category_id}
                emptyLabel="Uncategorized"
                options={availableCategories
                    .filter((category) => category.kind === data.kind)
                    .map((category) => ({
                        value: String(category.id),
                        label:
                            (category.parent
                                ? category.parent.name + ' / '
                                : '') +
                            category.name +
                            (category.archived_at ? ' (archived)' : ''),
                    }))}
                onChange={(category_id) => onChange({ category_id })}
                error={errors.category_id}
            />
            <TransactionChoice
                id={prefix + '-payee'}
                label="Payee (optional)"
                value={data.payee_id}
                emptyLabel="Not recorded"
                options={availablePayees.map((payee) => ({
                    value: String(payee.id),
                    label:
                        payee.name + (payee.archived_at ? ' (archived)' : ''),
                }))}
                onChange={(payee_id) => onChange({ payee_id })}
                error={errors.payee_id}
            />
            <div className="flex flex-wrap gap-2 sm:col-span-2">
                {(data.kind === 'income' || data.kind === 'expense') && (
                    <InlineReferenceCreate
                        entity="category"
                        team={team}
                        prefix={prefix}
                        initialKind={data.kind}
                        roots={availableCategories}
                        onCreated={(category) => {
                            setAddedCategories((previous) => [
                                ...previous,
                                category,
                            ]);
                            onChange({ category_id: String(category.id) });
                        }}
                    />
                )}
                <InlineReferenceCreate
                    entity="payee"
                    team={team}
                    prefix={prefix}
                    onCreated={(payee) => {
                        setAddedPayees((previous) => [...previous, payee]);
                        onChange({ payee_id: String(payee.id) });
                    }}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={prefix + '-description'}>Description</Label>
                <Input
                    id={prefix + '-description'}
                    value={data.description}
                    aria-invalid={!!errors.description}
                    aria-describedby={
                        errors.description
                            ? prefix + '-description-error'
                            : undefined
                    }
                    maxLength={255}
                    onChange={(event) =>
                        onChange({ description: event.target.value })
                    }
                />
                <InputError
                    id={prefix + '-description-error'}
                    message={errors.description}
                />
            </div>
            <div className="flex items-center gap-2 sm:col-span-2">
                <Checkbox
                    id={prefix + '-excluded'}
                    checked={data.excluded_from_analytics}
                    aria-invalid={!!errors.excluded_from_analytics}
                    aria-describedby={
                        errors.excluded_from_analytics
                            ? prefix + '-excluded-error'
                            : undefined
                    }
                    onCheckedChange={(checked) =>
                        onChange({ excluded_from_analytics: checked === true })
                    }
                />
                <Label htmlFor={prefix + '-excluded'}>
                    Exclude from spending analytics
                </Label>
                <InputError
                    id={prefix + '-excluded-error'}
                    message={errors.excluded_from_analytics}
                />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={prefix + '-reason'}>
                    Reason / audit note (optional)
                </Label>
                <Input
                    id={prefix + '-reason'}
                    value={data.reason}
                    aria-invalid={!!errors.reason}
                    aria-describedby={
                        errors.reason ? prefix + '-reason-error' : undefined
                    }
                    maxLength={2000}
                    onChange={(event) =>
                        onChange({ reason: event.target.value })
                    }
                />
                <InputError
                    id={prefix + '-reason-error'}
                    message={errors.reason}
                />
            </div>
        </>
    );
}

export function TransactionPagination({
    page,
    url,
}: {
    page: { current_page: number; last_page: number; total: number };
    url: (page: number) => string;
}) {
    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-3"
        >
            <p className="text-sm text-muted-foreground">
                Page {page.current_page} of {page.last_page} · {page.total}{' '}
                records
            </p>
            <div className="flex gap-2">
                {page.current_page > 1 && (
                    <Button variant="outline" asChild>
                        <Link href={url(page.current_page - 1)}>Previous</Link>
                    </Button>
                )}
                {page.current_page < page.last_page && (
                    <Button variant="outline" asChild>
                        <Link href={url(page.current_page + 1)}>Next</Link>
                    </Button>
                )}
            </div>
        </nav>
    );
}
