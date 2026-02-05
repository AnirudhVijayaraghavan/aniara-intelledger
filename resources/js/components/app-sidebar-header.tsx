import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Moon, Sun } from 'lucide-react';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() =>
                        updateAppearance(isDark ? 'light' : 'dark')
                    }
                    className="group h-9 w-9 cursor-pointer"
                    aria-label="Toggle color mode"
                >
                    <span className="relative flex h-5 w-5 items-center justify-center">
                        <Sun
                            className={cn(
                                'absolute h-5 w-5 transition-all duration-300',
                                isDark
                                    ? 'scale-0 opacity-0'
                                    : 'scale-100 opacity-100',
                            )}
                        />
                        <Moon
                            className={cn(
                                'absolute h-5 w-5 transition-all duration-300',
                                isDark
                                    ? 'scale-100 opacity-100'
                                    : 'scale-0 opacity-0',
                            )}
                        />
                    </span>
                </Button>
            </div>
        </header>
    );
}
