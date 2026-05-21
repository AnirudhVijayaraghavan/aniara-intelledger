import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-background text-foreground lg:grid-cols-[0.95fr_1fr]">
            <aside className="hidden border-r border-border bg-muted lg:flex lg:flex-col lg:justify-between lg:p-10">
                <Link href={home()} className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <AppLogoIcon className="size-7" />
                    </span>
                    <span className="text-lg font-semibold">
                        Aniara Intelledger
                    </span>
                </Link>

                <div className="space-y-8">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Ledger snapshot
                        </p>
                        <p className="mt-2 text-4xl font-semibold">$142,880</p>
                    </div>
                    <div className="grid grid-cols-8 items-end gap-2 rounded-md border border-border bg-card p-5">
                        {[28, 40, 36, 52, 48, 66, 72, 84].map(
                            (height, index) => (
                                <div
                                    key={index}
                                    className="rounded-sm bg-primary"
                                    style={{ height }}
                                />
                            ),
                        )}
                    </div>
                    <div className="grid gap-3 text-sm">
                        <div className="flex items-center justify-between rounded-md border border-border bg-card px-4 py-3">
                            <span>Household access</span>
                            <span className="font-semibold text-primary">
                                Protected
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-md border border-border bg-card px-4 py-3">
                            <span>Market watchlists</span>
                            <span className="font-semibold text-accent-foreground">
                                Ready
                            </span>
                        </div>
                    </div>
                </div>

                <p className="text-sm leading-6 text-muted-foreground">
                    Private finance workspace for accounts, portfolios, budgets,
                    and net worth.
                </p>
            </aside>

            <section className="flex items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm animate-in duration-700 fade-in slide-in-from-bottom-3">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4 text-center lg:hidden">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-1 flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-7" />
                                </div>
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2">
                                <p className="text-sm font-semibold">
                                    Aniara Intelledger
                                </p>
                            </div>
                        </div>

                        <div className="space-y-2 text-center lg:text-left">
                            <h1 className="text-2xl font-semibold">{title}</h1>
                            <p className="text-sm leading-6 text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </section>
        </div>
    );
}
