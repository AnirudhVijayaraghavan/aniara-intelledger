import { Head, Link } from '@inertiajs/react';
import { Archive, Pencil } from 'lucide-react';
import { useState } from 'react';
import AccountBalances from '@/components/account-balances';
import type { AccountBalancesProps } from '@/components/account-balances';
import ArchiveAccountModal from '@/components/archive-account-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/accounts';

type Props = AccountBalancesProps & {
    canManage: boolean;
};

export default function AccountDetails({
    team,
    account,
    canManage,
    ...balanceProps
}: Props) {
    const [archiveOpen, setArchiveOpen] = useState(false);

    return (
        <>
            <Head title={account.name} />
            <div className="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={account.name} description={team.name} />
                    <div className="flex flex-wrap items-center gap-2">
                        {account.archived_at ? (
                            <Badge variant="secondary">Archived</Badge>
                        ) : (
                            canManage && (
                                <>
                                    <Button asChild variant="outline">
                                        <Link
                                            href={edit([team.slug, account.id])}
                                        >
                                            <Pencil />
                                            Edit account
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => setArchiveOpen(true)}
                                    >
                                        <Archive />
                                        Archive
                                    </Button>
                                </>
                            )
                        )}
                    </div>
                </div>
                <dl className="grid gap-6 rounded-md border bg-card p-6 sm:grid-cols-2">
                    <div className="grid gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Institution
                        </dt>
                        <dd>{account.institution?.name ?? 'Not recorded'}</dd>
                    </div>
                    <div className="grid gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Account type
                        </dt>
                        <dd>
                            {account.typeLabel} ·{' '}
                            {account.isLiability ? 'Liability' : 'Asset'}
                        </dd>
                    </div>
                    <div className="grid gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Currency
                        </dt>
                        <dd>{account.currency_code}</dd>
                    </div>
                    <div className="grid gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Recorded holders
                        </dt>
                        <dd>
                            <ul className="grid gap-1">
                                {account.owners.map((owner) => (
                                    <li key={owner.id}>{owner.owner_name}</li>
                                ))}
                            </ul>
                        </dd>
                    </div>
                </dl>
                {account.archived_at && (
                    <p className="text-sm text-muted-foreground">
                        This account is archived. Its financial history and
                        recorded holders are preserved.
                    </p>
                )}
                <AccountBalances
                    team={team}
                    account={account}
                    {...balanceProps}
                />
                <Button asChild variant="link" className="self-start px-0">
                    <Link href={index(team.slug)}>Back to accounts</Link>
                </Button>
            </div>
            {canManage && !account.archived_at && (
                <ArchiveAccountModal
                    team={team}
                    account={account}
                    open={archiveOpen}
                    onOpenChange={setArchiveOpen}
                />
            )}
        </>
    );
}

AccountDetails.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Accounts', href: index(props.team.slug) },
        {
            title: props.account.name,
            href: show([props.team.slug, props.account.id]),
        },
    ],
});
