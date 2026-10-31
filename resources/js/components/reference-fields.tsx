import { FinancialChoice } from '@/components/financial-choice';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TransactionCategory } from '@/types/transactions';

export type ReferenceInput = { name: string; kind: string; parent_id: string };
export function ReferenceFields({
    prefix,
    category,
    data,
    onChange,
    errors,
    roots,
    recordId,
    retainedParentId,
    lockKind,
    hasChildren,
    kindHint,
}: {
    prefix: string;
    category: boolean;
    data: ReferenceInput;
    onChange: (patch: Partial<ReferenceInput>) => void;
    errors: Record<string, string | undefined>;
    roots: TransactionCategory[];
    recordId?: number;
    retainedParentId?: number | null;
    lockKind?: boolean;
    hasChildren?: boolean;
    kindHint?: string;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={prefix + '-name'}>Name</Label>
                <Input
                    id={prefix + '-name'}
                    value={data.name}
                    onChange={(event) => onChange({ name: event.target.value })}
                    maxLength={255}
                    required
                    aria-invalid={!!errors.name}
                />
                <InputError message={errors.name} />
            </div>
            {category && (
                <>
                    <FinancialChoice
                        id={prefix + '-kind'}
                        label="Kind"
                        value={data.kind}
                        options={[
                            { value: 'expense', label: 'Expense' },
                            { value: 'income', label: 'Income' },
                        ]}
                        onChange={(kind) => onChange({ kind, parent_id: '' })}
                        error={errors.kind}
                        disabled={lockKind}
                    />
                    {lockKind && (
                        <p className="text-sm text-muted-foreground">
                            {kindHint ||
                                'The kind is fixed while transactions or subcategories reference this category.'}
                        </p>
                    )}
                    <FinancialChoice
                        id={prefix + '-parent'}
                        label="Parent category"
                        value={data.parent_id}
                        emptyLabel="Root category"
                        options={roots
                            .filter(
                                (root) =>
                                    root.id !== recordId &&
                                    root.parent_id === null &&
                                    root.kind === data.kind &&
                                    (!root.archived_at ||
                                        root.id === retainedParentId),
                            )
                            .map((root) => ({
                                value: String(root.id),
                                label:
                                    root.name +
                                    (root.archived_at ? ' (archived)' : ''),
                            }))}
                        onChange={(parent_id) => onChange({ parent_id })}
                        error={errors.parent_id}
                        disabled={hasChildren}
                    />
                    <p className="text-sm text-muted-foreground">
                        {hasChildren
                            ? 'A category with subcategories must remain a root category.'
                            : 'Subcategories can have one root parent with the same kind.'}
                    </p>
                </>
            )}
        </>
    );
}
