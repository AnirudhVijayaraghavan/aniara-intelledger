import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

const highlights = [
    {
        title: 'Portfolio truth',
        description:
            'See every position, watchlist name, and allocation in a single view.',
    },
    {
        title: 'Net worth timeline',
        description:
            'Track assets, liabilities, and cashflow with clear time-series insight.',
    },
    {
        title: 'Daily briefings',
        description:
            'Pre-market updates tied directly to your holdings and watchlist.',
    },
];

const sources = [
    'Chase',
    'ICICI',
    'Robinhood',
    'Fidelity',
    'Employer 401k',
    'Manual entry',
];

const metrics = [
    {
        label: 'Coverage',
        value: 'US + India',
        detail: 'Brokerage, banking, retirement',
    },
    {
        label: 'Cadence',
        value: 'Daily',
        detail: 'Briefings and watchlist signals',
    },
    {
        label: 'Security',
        value: 'Bank grade',
        detail: 'Encryption and access controls',
    },
];

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="relative min-h-svh bg-slate-50 text-slate-950 dark:bg-[#05080f] dark:text-slate-100">
            <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(70rem_circle_at_top,_rgba(15,23,42,0.08),_transparent_60%)] dark:bg-[radial-gradient(70rem_circle_at_top,_rgba(16,185,129,0.12),_transparent_60%)]" />
            <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(45rem_circle_at_80%_20%,_rgba(14,165,233,0.1),_transparent_60%)] dark:bg-[radial-gradient(45rem_circle_at_20%_10%,_rgba(56,189,248,0.15),_transparent_60%)]" />
            <div className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(transparent_1px,_rgba(15,23,42,0.04)_1px)] bg-[size:28px_28px] opacity-60 dark:bg-[linear-gradient(transparent_1px,_rgba(148,163,184,0.08)_1px)]" />
            <div className="mx-auto grid w-full max-w-6xl gap-6 px-6 py-6 font-['IBM_Plex_Sans',_ui-sans-serif] lg:grid-cols-[1.05fr_0.95fr] lg:items-start">
                <aside className="hidden flex-col gap-6 lg:flex">
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-3 text-sm font-semibold text-slate-900 dark:text-white"
                        viewTransition
                    >
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <AppLogoIcon className="size-6 fill-current" />
                        </div>
                        IntelLedger
                    </Link>
                    <div className="flex flex-col gap-3">
                        <p className="font-['IBM_Plex_Mono',_ui-monospace] text-xs uppercase tracking-[0.32em] text-slate-500 dark:text-slate-400">
                            Secure access
                        </p>
                        <h1 className="font-['Space_Grotesk',_ui-sans-serif] text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
                            Your global finance OS, ready for every decision.
                        </h1>
                        <p className="text-sm text-slate-600 dark:text-slate-300">
                            Consolidate US and India accounts, align your net
                            worth, and stay ahead with daily market briefings.
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {highlights.map((highlight) => (
                            <div
                                key={highlight.title}
                                className="rounded-2xl border border-slate-200/70 bg-white/80 p-2 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70"
                            >
                                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">
                                    {highlight.title}
                                </h2>
                                <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                    {highlight.description}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="flex flex-wrap gap-2 text-[0.7rem] text-slate-600 dark:text-slate-300">
                        {sources.map((source) => (
                            <span
                                key={source}
                                className="rounded-full border border-slate-200/70 bg-white/80 px-3 py-0.5 dark:border-slate-800/70 dark:bg-slate-900/70"
                            >
                                {source}
                            </span>
                        ))}
                    </div>
                    <dl className="grid gap-3 sm:grid-cols-3">
                        {metrics.map((metric) => (
                            <div
                                key={metric.label}
                                className="rounded-2xl border border-slate-200/70 bg-white/80 p-2 backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70"
                            >
                                <dt className="text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                    {metric.label}
                                </dt>
                                <dd className="text-lg font-semibold text-slate-900 dark:text-white">
                                    {metric.value}
                                </dd>
                                <dd className="text-xs text-slate-500 dark:text-slate-400">
                                    {metric.detail}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </aside>
                <section className="flex flex-col gap-4">
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-900 lg:hidden dark:text-white"
                        viewTransition
                    >
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <AppLogoIcon className="size-6 fill-current" />
                        </div>
                        IntelLedger
                    </Link>
                    <div className="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-[0_30px_90px_-70px_rgba(15,23,42,0.7)] backdrop-blur animate-in fade-in-0 slide-in-from-bottom-3 duration-500 dark:border-slate-800/70 dark:bg-slate-900/70">
                        <div className="flex flex-col gap-2">
                            <p className="font-['IBM_Plex_Mono',_ui-monospace] text-xs uppercase tracking-[0.3em] text-emerald-700/80 dark:text-emerald-300/80">
                                IntelLedger access
                            </p>
                            <div className="space-y-2">
                                <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-2xl font-semibold text-slate-900 dark:text-white">
                                    {title}
                                </h2>
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    {description}
                                </p>
                            </div>
                        </div>
                        <div className="mt-6">{children}</div>
                        <div className="mt-6 rounded-2xl border border-slate-200/70 bg-slate-50/80 p-3 text-xs text-slate-600 dark:border-slate-800/70 dark:bg-slate-950/60 dark:text-slate-300">
                            Secure, private, and designed for a disciplined
                            investing workflow.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
}
