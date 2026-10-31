import { Head, Link } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useState } from 'react';
import LedgerPreview from '@/components/ledger-preview';
import ConsolidatedOverview from '@/components/consolidated-overview';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index } from '@/routes/accounts';
import type { DashboardInvitation } from '@/types';
import type {
    FinancialTeam,
    NetWorthOverview,
    ConsolidatedNetWorthOverview,
} from '@/types/finance';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    team: FinancialTeam;
    financialOverview: NetWorthOverview;
    consolidatedOverview: ConsolidatedNetWorthOverview;
    canManageExchangeRates: boolean;
    canManageAccounts: boolean;
};

export default function Dashboard({
    pendingInvitations = [],
    team,
    financialOverview,
    consolidatedOverview,
    canManageExchangeRates,
    canManageAccounts,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="grid gap-2">
                        <h1 className="text-xl font-semibold">
                            Household overview
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {team.name} · Valuation date{' '}
                            <time dateTime={financialOverview.asOfDate}>
                                {financialOverview.asOfDate}
                            </time>
                        </p>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Latest valid observations through this date.
                            Archived accounts remain included. Original USD and
                            INR balances are preserved below.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={index(team.slug)}>
                            <Wallet />
                            {canManageAccounts
                                ? 'Manage accounts'
                                : 'View accounts'}
                        </Link>
                    </Button>
                </header>
                <ConsolidatedOverview
                    overview={consolidatedOverview}
                    team={team}
                    canManageRates={canManageExchangeRates}
                />
                <LedgerPreview
                    overview={financialOverview}
                    team={team}
                    canManageAccounts={canManageAccounts}
                />
            </div>
        </>
    );
}

Dashboard.layout = (props: Props) => ({
    breadcrumbs: [{ title: 'Dashboard', href: dashboard(props.team.slug) }],
});
