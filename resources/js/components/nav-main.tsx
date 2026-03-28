import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="px-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-sidebar-foreground/55">
                Workspace
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className="rounded-xl border border-transparent text-sidebar-foreground/78 hover:border-sidebar-border/60 hover:bg-sidebar-accent hover:text-sidebar-foreground data-[active=true]:border-sidebar-border/80 data-[active=true]:bg-[linear-gradient(135deg,var(--color-intel-mint-soft),var(--color-intel-amber-soft))] data-[active=true]:text-sidebar-foreground data-[active=true]:shadow-sm dark:data-[active=true]:bg-[linear-gradient(135deg,rgba(75,216,204,0.18),rgba(255,191,105,0.10))]"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
