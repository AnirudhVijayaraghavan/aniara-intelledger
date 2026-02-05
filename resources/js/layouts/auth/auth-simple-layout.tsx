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
        <div className="relative min-h-svh bg-background text-foreground">
            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(203,243,240,0.45),rgba(255,255,255,0.95),rgba(255,191,105,0.1))] dark:bg-[linear-gradient(135deg,rgba(15,31,30,0.95),rgba(11,22,21,1),rgba(46,196,182,0.08))]" />
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.12),transparent_55%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.18),transparent_60%)]" />

            <div className="relative grid min-h-svh lg:grid-cols-[1.05fr_0.95fr]">
                <aside className="hidden h-full flex-col justify-between border-r border-border bg-[#cbf3f0]/25 px-10 py-12 lg:flex">
                    <div className="flex flex-col gap-10">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-3"
                        >
                            <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-[#2ec4b6]/25">
                                <AppLogoIcon className="size-6 fill-current" />
                            </span>
                            <span className="text-xs font-semibold tracking-[0.4em] text-muted-foreground">
                                INTELLEDGER
                            </span>
                        </Link>

                        <div className="space-y-4">
                            <p className="text-xs font-semibold uppercase tracking-[0.3em] text-[#ff9f1c]">
                                Ledger access
                            </p>
                            <h2 className="text-4xl font-semibold leading-tight">
                                Secure entry to a unified investment ledger.
                            </h2>
                            <p className="text-lg text-muted-foreground">
                                A dependable workspace for investors tracking
                                US + India portfolios with daily intelligence.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-4">
                        {[
                            {
                                label: 'Coverage',
                                value: 'US + India markets',
                            },
                            {
                                label: 'Cadence',
                                value: 'Daily briefings',
                            },
                            {
                                label: 'Focus',
                                value: 'Net worth clarity',
                            },
                        ].map((item) => (
                            <div
                                key={item.label}
                                className="rounded-2xl border border-border bg-card/80 p-4"
                            >
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    {item.label}
                                </p>
                                <p className="mt-2 text-lg font-semibold">
                                    {item.value}
                                </p>
                            </div>
                        ))}
                    </div>
                </aside>

                <div className="flex min-h-svh items-center justify-center px-6 py-10 lg:px-12 lg:py-0">
                    <div className="w-full max-w-sm rounded-3xl border border-border bg-card/90 p-6 shadow-2xl shadow-[#2ec4b6]/10 backdrop-blur animate-in slide-in-from-left-6 fade-in duration-700 min-h-[520px] flex flex-col">
                        <div className="flex flex-col gap-6">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={home()}
                                    className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-[#2ec4b6]/25"
                                >
                                    <AppLogoIcon className="size-6 fill-current" />
                                    <span className="sr-only">{title}</span>
                                </Link>
                                <div>
                                    <p className="text-sm font-semibold">
                                        IntelLedger
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Personal finance intelligence
                                    </p>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <h1 className="text-2xl font-semibold text-foreground">
                                    {title}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            <div className="flex-1">{children}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
