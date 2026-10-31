import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BarChart3,
    LayoutGrid,
    List,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppVersion from '@/components/app-version';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { index as ratesIndex } from '@/routes/exchange-rates';
import { index as activityIndex } from '@/routes/financial-activity';
import { index as transactionsIndex } from '@/routes/transactions';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
    ];

    if (page.props.currentTeam) {
        mainNavItems.push({
            title: 'Accounts',
            href: accountsIndex(page.props.currentTeam.slug),
            icon: Wallet,
        });
        mainNavItems.push({
            title: 'FX rates',
            href: ratesIndex(page.props.currentTeam.slug),
            icon: ArrowLeftRight,
        });
        mainNavItems.push({
            title: 'Transactions',
            href: transactionsIndex(page.props.currentTeam.slug),
            icon: List,
        });
        mainNavItems.push({
            title: 'Spending & Income',
            href: activityIndex(page.props.currentTeam.slug),
            icon: BarChart3,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
                <AppVersion className="px-2 pb-1 group-data-[collapsible=icon]:hidden" />
            </SidebarFooter>
        </Sidebar>
    );
}
