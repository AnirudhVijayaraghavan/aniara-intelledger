import { Button } from '@/components/ui/button';
import { cn, toUrl } from '@/lib/utils';
import { useActiveUrl } from '@/hooks/use-active-url';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: null,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { urlIsActive } = useActiveUrl();

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <div className="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-[0_24px_60px_-48px_rgba(15,23,42,0.5)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
                <p className="text-xs uppercase tracking-[0.32em] text-emerald-600/80 dark:text-emerald-300/80">
                    IntelLedger settings
                </p>
                <h1 className="mt-3 text-2xl font-semibold text-slate-900 dark:text-white">
                    Settings
                </h1>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Manage your profile, security, and appearance preferences.
                </p>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                <aside className="w-full">
                    <nav
                        className="flex flex-col gap-2 rounded-2xl border border-slate-200/70 bg-white/80 p-2 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'w-full justify-start rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-200/60 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white',
                                    urlIsActive(item.href)
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200'
                                        : '',
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <div className="flex-1">
                    <section className="space-y-4">{children}</section>
                </div>
            </div>
        </div>
    );
}
