import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Moon, Sun } from 'lucide-react';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <AppearanceMenu />
        </header>
    );
}

function AppearanceMenu() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const Icon = isDark ? Sun : Moon;
    const nextAppearance = isDark ? 'light' : 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            className="ml-auto size-9 text-muted-foreground hover:bg-secondary hover:text-secondary-foreground"
            onClick={() => updateAppearance(nextAppearance)}
            aria-label={`Switch to ${nextAppearance} appearance`}
        >
            <Icon className="size-4" />
        </Button>
    );
}
