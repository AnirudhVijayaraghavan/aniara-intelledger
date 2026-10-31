import { Head, Link, useForm } from '@inertiajs/react';
import { Archive, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FinancialChoice } from '@/components/financial-choice';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ReferenceFields } from '@/components/reference-fields';
import type { ReferenceInput } from '@/components/reference-fields';
import { TransactionPagination } from '@/components/transaction-fields';
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
import {
    archive as archiveCategory,
    index as categoryIndex,
    store as storeCategory,
    update as updateCategory,
} from '@/routes/categories';
import {
    archive as archivePayee,
    index as payeeIndex,
    store as storePayee,
    update as updatePayee,
} from '@/routes/payees';
import { index as transactionsIndex } from '@/routes/transactions';
import type { FinancialTeam } from '@/types/finance';
import type { Paginated, TransactionCategory } from '@/types/transactions';

type Reference = {
    id: number;
    name: string;
    archived_at: string | null;
    transactions_count: number;
    kind?: 'income' | 'expense';
    parent_id?: number | null;
    parent?: { id: number; name: string } | null;
    has_children?: boolean;
};
export type ReferenceManagerProps = {
    team: FinancialTeam;
    records: Paginated<Reference>;
    roots?: TransactionCategory[];
    canManage: boolean;
    filters: {
        search?: string | null;
        status?: string | null;
        kind?: string | null;
    };
};
function Editor({
    entity,
    team,
    roots,
    selected,
    close,
}: {
    entity: 'categories' | 'payees';
    team: FinancialTeam;
    roots: TransactionCategory[];
    selected: Reference | null;
    close: () => void;
}) {
    const category = entity === 'categories';
    const form = useForm<ReferenceInput>({
        name: selected?.name || '',
        kind: selected?.kind || 'expense',
        parent_id: selected?.parent_id ? String(selected.parent_id) : '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) =>
            category
                ? { ...data, parent_id: data.parent_id || null }
                : { name: data.name },
        );
        const options = { onSuccess: close };
        if (selected) {
            form.put(
                (category ? updateCategory : updatePayee).url([
                    team.slug,
                    selected.id,
                ]),
                options,
            );
        } else {
            form.post(
                (category ? storeCategory : storePayee).url(team.slug),
                options,
            );
        }
    };
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {selected ? 'Edit' : 'Create'}{' '}
                        {category ? 'category' : 'payee'}
                    </DialogTitle>
                    <DialogDescription>
                        {category
                            ? 'Classify income and expenses with one level of subcategories.'
                            : 'Record a merchant, employer, person, or other counterparty. Duplicate names are permitted.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <ReferenceFields
                        prefix="reference-editor"
                        category={category}
                        data={form.data}
                        onChange={(patch) =>
                            form.setData({ ...form.data, ...patch })
                        }
                        errors={form.errors}
                        roots={roots}
                        recordId={selected?.id}
                        retainedParentId={selected?.parent_id}
                        lockKind={
                            !!selected &&
                            (selected.transactions_count > 0 ||
                                selected.has_children)
                        }
                        hasChildren={selected?.has_children}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Cancel
                        </Button>
                        <Button disabled={form.processing}>
                            Save {category ? 'category' : 'payee'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
function ArchiveReference({
    entity,
    team,
    selected,
    close,
}: {
    entity: 'categories' | 'payees';
    team: FinancialTeam;
    selected: Reference;
    close: () => void;
}) {
    const form = useForm({ archive: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            (entity === 'categories' ? archiveCategory : archivePayee).url([
                team.slug,
                selected.id,
            ]),
            { onSuccess: close },
        );
    };
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive {selected.name}?</DialogTitle>
                    <DialogDescription>
                        Historical transaction references are preserved.{' '}
                        {entity === 'categories'
                            ? 'Archive active subcategories first.'
                            : 'This payee will no longer be available for new assignments.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <InputError message={form.errors.archive} />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Keep active
                        </Button>
                        <Button disabled={form.processing}>
                            Confirm archive
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
export default function FinancialReferenceManager({
    entity,
    team,
    records,
    roots = [],
    filters,
    canManage,
}: ReferenceManagerProps & { entity: 'categories' | 'payees' }) {
    const category = entity === 'categories';
    const title = category ? 'Categories' : 'Payees';
    const list = category ? categoryIndex : payeeIndex;
    const [editorOpen, setEditorOpen] = useState(false);
    const [selected, setSelected] = useState<Reference | null>(null);
    const [archiving, setArchiving] = useState<Reference | null>(null);
    const search = useForm({
        search: filters.search || '',
        status: filters.status || 'active',
        kind: filters.kind || '',
    });
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        search.transform((data) =>
            category ? data : { search: data.search, status: data.status },
        );
        search.get(list.url(team.slug), {
            preserveState: true,
            preserveScroll: true,
        });
    };
    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description={`Household ${title.toLowerCase()} for ${team.name}.`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={transactionsIndex(team.slug)}>
                                Transactions
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={(category ? payeeIndex : categoryIndex)(
                                    team.slug,
                                )}
                            >
                                {category ? 'Payees' : 'Categories'}
                            </Link>
                        </Button>
                        {canManage && (
                            <Button
                                onClick={() => {
                                    setSelected(null);
                                    setEditorOpen(true);
                                }}
                            >
                                <Plus />
                                New {category ? 'category' : 'payee'}
                            </Button>
                        )}
                    </div>
                </div>
                <form
                    onSubmit={submitSearch}
                    className="grid gap-4 rounded-md border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="reference-search">Search</Label>
                        <Input
                            id="reference-search"
                            value={search.data.search}
                            maxLength={100}
                            onChange={(event) =>
                                search.setData('search', event.target.value)
                            }
                            placeholder={
                                category
                                    ? 'Category or parent name'
                                    : 'Payee name'
                            }
                        />
                        <InputError message={search.errors.search} />
                    </div>
                    <FinancialChoice
                        id="reference-status"
                        label="Status"
                        value={search.data.status}
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'archived', label: 'Archived' },
                            { value: 'all', label: 'All' },
                        ]}
                        onChange={(status) => search.setData('status', status)}
                        error={search.errors.status}
                    />
                    {category && (
                        <FinancialChoice
                            id="reference-kind"
                            label="Kind"
                            value={search.data.kind}
                            emptyLabel="All kinds"
                            options={[
                                { value: 'income', label: 'Income' },
                                { value: 'expense', label: 'Expense' },
                            ]}
                            onChange={(kind) => search.setData('kind', kind)}
                            error={search.errors.kind}
                        />
                    )}
                    <div className="flex items-end gap-2">
                        <Button disabled={search.processing}>Search</Button>
                        <Button asChild variant="outline">
                            <Link href={list(team.slug)} preserveState={false}>
                                Clear
                            </Link>
                        </Button>
                    </div>
                </form>
                {!records.data.length ? (
                    <div className="grid gap-2 rounded-md border bg-card p-8">
                        <h2 className="font-medium">
                            No {title.toLowerCase()} found
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Create a household reference or adjust the search
                            and archive filters.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="p-4 font-medium">
                                        Name / hierarchy
                                    </th>
                                    {category && (
                                        <th className="p-4 font-medium">
                                            Kind
                                        </th>
                                    )}
                                    <th className="p-4 font-medium">Status</th>
                                    <th className="p-4 font-medium">
                                        Transaction references
                                    </th>
                                    {canManage && (
                                        <th className="p-4 font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {records.data.map((record) => (
                                    <tr key={record.id}>
                                        <td className="p-4 font-medium">
                                            {record.parent && (
                                                <span className="text-muted-foreground">
                                                    {record.parent.name} /{' '}
                                                </span>
                                            )}
                                            {record.name}
                                            {category && !record.parent && (
                                                <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                    Root category
                                                </p>
                                            )}
                                        </td>
                                        {category && (
                                            <td className="p-4 capitalize">
                                                {record.kind}
                                            </td>
                                        )}
                                        <td className="p-4">
                                            <Badge variant="secondary">
                                                {record.archived_at
                                                    ? 'Archived'
                                                    : 'Active'}
                                            </Badge>
                                        </td>
                                        <td className="p-4 tabular-nums">
                                            {record.transactions_count}
                                        </td>
                                        {canManage && (
                                            <td className="p-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setSelected(record);
                                                            setEditorOpen(true);
                                                        }}
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                    {!record.archived_at && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setArchiving(
                                                                    record,
                                                                )
                                                            }
                                                        >
                                                            <Archive />
                                                            Archive
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <TransactionPagination
                    page={records}
                    url={(page) =>
                        list.url(team.slug, { query: { ...filters, page } })
                    }
                />
            </div>
            {editorOpen && (
                <Editor
                    entity={entity}
                    team={team}
                    roots={roots}
                    selected={selected}
                    close={() => setEditorOpen(false)}
                />
            )}
            {archiving && (
                <ArchiveReference
                    entity={entity}
                    team={team}
                    selected={archiving}
                    close={() => setArchiving(null)}
                />
            )}
        </>
    );
}
