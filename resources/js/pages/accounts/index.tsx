import { Head, Link, router } from '@inertiajs/react';
import { Plus, Wallet } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index, show } from '@/routes/accounts';
import type {
    AccountType,
    FinancialAccount,
    FinancialTeam,
} from '@/types/finance';

type Props = {
    team: FinancialTeam;
    accounts: {
        data: FinancialAccount[];
        current_page: number;
        last_page: number;
        total: number;
    };
    types: AccountType[];
    filters: { type: string; status: string };
    canManage: boolean;
};

export default function Accounts({
    team,
    accounts,
    types,
    filters,
    canManage,
}: Props) {
    const filter = (key: 'type' | 'status', value: string) => {
        router.get(
            index.url(team.slug),
            { ...filters, [key]: value === 'all' ? '' : value },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Accounts" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Accounts"
                        description={`The financial accounts recorded for ${team.name}.`}
                    />
                    {canManage && (
                        <Button asChild>
                            <Link href={create(team.slug)}>
                                <Plus />
                                Add account
                            </Link>
                        </Button>
                    )}
                </div>
                <div className="flex flex-wrap gap-4">
                    <div className="grid w-48 gap-2">
                        <Label htmlFor="account-type-filter">
                            Account type
                        </Label>
                        <Select
                            value={filters.type || 'all'}
                            onValueChange={(value) => filter('type', value)}
                        >
                            <SelectTrigger id="account-type-filter">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                {types.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={type.value}
                                    >
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid w-40 gap-2">
                        <Label htmlFor="account-status-filter">Status</Label>
                        <Select
                            value={filters.status}
                            onValueChange={(value) => filter('status', value)}
                        >
                            <SelectTrigger id="account-status-filter">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="archived">
                                    Archived
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                {accounts.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-md border border-dashed px-6 py-14 text-center">
                        <Wallet className="size-7 text-muted-foreground" />
                        <h2 className="text-lg font-medium">
                            {filters.status === 'archived'
                                ? 'No archived accounts'
                                : 'No accounts to show'}
                        </h2>
                        <p className="max-w-md text-sm text-muted-foreground">
                            {filters.type
                                ? 'Choose another account type to see more accounts.'
                                : canManage && filters.status === 'active'
                                  ? 'Add your first account and its recorded holders to start your household ledger.'
                                  : 'Accounts recorded for this household will appear here.'}
                        </p>
                        {canManage && filters.status === 'active' && (
                            <Button asChild variant="outline">
                                <Link href={create(team.slug)}>
                                    <Plus />
                                    Add account
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Household financial accounts
                            </caption>
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    {[
                                        'Account',
                                        'Institution',
                                        'Type',
                                        'Currency',
                                        'Recorded holders',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {accounts.data.map((account) => (
                                    <tr
                                        key={account.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="min-w-44 px-4 py-4">
                                            <Link
                                                href={show([
                                                    team.slug,
                                                    account.id,
                                                ])}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {account.name}
                                            </Link>
                                            {account.archived_at && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    Archived
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-4">
                                            {account.institution?.name ??
                                                'Not recorded'}
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap">
                                            {account.typeLabel}
                                        </td>
                                        <td className="px-4 py-4 font-medium">
                                            {account.currency_code}
                                        </td>
                                        <td className="min-w-40 px-4 py-4">
                                            {account.owners
                                                .map(
                                                    (owner) => owner.owner_name,
                                                )
                                                .join(', ')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {accounts.last_page > 1 && (
                    <nav
                        aria-label="Account pages"
                        className="flex items-center justify-between gap-4"
                    >
                        <Button
                            asChild
                            variant="outline"
                            disabled={accounts.current_page === 1}
                        >
                            <Link
                                href={index(team.slug, {
                                    query: {
                                        ...filters,
                                        page: Math.max(
                                            1,
                                            accounts.current_page - 1,
                                        ),
                                    },
                                })}
                                aria-disabled={accounts.current_page === 1}
                                className={
                                    accounts.current_page === 1
                                        ? 'pointer-events-none opacity-50'
                                        : ''
                                }
                            >
                                Previous
                            </Link>
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {accounts.current_page} of {accounts.last_page}
                        </span>
                        <Button
                            asChild
                            variant="outline"
                            disabled={
                                accounts.current_page === accounts.last_page
                            }
                        >
                            <Link
                                href={index(team.slug, {
                                    query: {
                                        ...filters,
                                        page: Math.min(
                                            accounts.last_page,
                                            accounts.current_page + 1,
                                        ),
                                    },
                                })}
                                aria-disabled={
                                    accounts.current_page === accounts.last_page
                                }
                                className={
                                    accounts.current_page === accounts.last_page
                                        ? 'pointer-events-none opacity-50'
                                        : ''
                                }
                            >
                                Next
                            </Link>
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}

Accounts.layout = (props: Props) => ({
    breadcrumbs: [{ title: 'Accounts', href: index(props.team.slug) }],
});
