import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useActiveUrl } from '@/hooks/use-active-url';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { urlIsActive } = useActiveUrl();

    return (
        <SidebarGroup className="px-2 py-1">
            <SidebarGroupLabel className="font-['IBM_Plex_Mono',_ui-monospace] text-[0.65rem] uppercase tracking-[0.32em] text-slate-500 dark:text-slate-400">
                Platform
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={urlIsActive(item.href)}
                            tooltip={{ children: item.title }}
                            className="rounded-xl text-slate-600 transition-colors hover:bg-slate-200/60 hover:text-slate-900 data-[active=true]:bg-emerald-500/10 data-[active=true]:text-emerald-700 dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white dark:data-[active=true]:text-emerald-200 [&>svg]:text-slate-400 data-[active=true]:[&>svg]:text-emerald-600 dark:[&>svg]:text-slate-400 dark:data-[active=true]:[&>svg]:text-emerald-300"
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
