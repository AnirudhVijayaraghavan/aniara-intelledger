import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const overviewStats = [
    {
        label: 'Net worth',
        value: '$1.24M',
        change: '+3.2% MoM',
        detail: 'USD base + INR view',
    },
    {
        label: 'Cashflow',
        value: '+$8.4k',
        change: '+$1.1k vs last month',
        detail: 'Income minus expenses',
    },
    {
        label: 'Debt',
        value: '$214k',
        change: '-0.8% MoM',
        detail: 'Loans + credit lines',
    },
];

const signals = [
    {
        label: 'Strategy',
        title: 'Trim mega-cap tech +3.1% weight',
        detail: 'Rebalance toward target allocation without reducing growth.',
        tone: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'Alert',
        title: 'Swing-trade trigger hit on watchlist',
        detail: 'Volume spike and price break detected overnight.',
        tone: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    },
    {
        label: 'News',
        title: 'Macro recap aligned to holdings',
        detail: 'Key headlines mapped to your US + India exposure.',
        tone: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
];

const watchlistMoves = [
    { symbol: 'AAPL', name: 'Apple', price: '$192.34', change: '+1.4%' },
    { symbol: 'NVDA', name: 'Nvidia', price: '$853.21', change: '-0.8%' },
    {
        symbol: 'RELIANCE',
        name: 'Reliance',
        price: '₹2,951',
        change: '+0.6%',
    },
];

const cadence = [
    {
        title: 'Daily pre-market briefing',
        timing: '06:30 ET / 17:00 IST',
        detail: 'Overnight news, movers, and watchlist alerts.',
    },
    {
        title: 'Weekly allocation review',
        timing: 'Friday close',
        detail: 'Risk posture, drift, and rebalancing actions.',
    },
    {
        title: 'Monthly net worth close',
        timing: 'Month-end',
        detail: 'Full cashflow and balance sheet summary.',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-hidden p-6">
                <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                    <section className="relative overflow-hidden rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 shadow-[0_30px_90px_-70px_rgba(15,23,42,0.7)] backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex flex-col gap-2">
                                <p className="font-['IBM_Plex_Mono',_ui-monospace] text-xs uppercase tracking-[0.3em] text-emerald-600/80 dark:text-emerald-300/80">
                                    Morning briefing
                                </p>
                                <h1 className="font-['Space_Grotesk',_ui-sans-serif] text-3xl font-semibold text-slate-900 dark:text-white">
                                    Global portfolio overview
                                </h1>
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    Consolidated US + India positions, cash,
                                    and liabilities with actionable signals.
                                </p>
                            </div>
                            <div className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:text-emerald-200">
                                Updated 06:30 ET / 17:00 IST
                            </div>
                        </div>
                        <div className="mt-6 grid gap-4 lg:grid-cols-3">
                            {overviewStats.map((stat) => (
                                <div
                                    key={stat.label}
                                    className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/70"
                                >
                                    <p className="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                                        {stat.label}
                                    </p>
                                    <div className="mt-2 flex items-end justify-between gap-3">
                                        <span className="text-2xl font-semibold text-slate-900 dark:text-white">
                                            {stat.value}
                                        </span>
                                        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            {stat.change}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        {stat.detail}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-6 rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 dark:border-slate-800/70 dark:bg-slate-950/60">
                            <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>Net worth timeline</span>
                                <span>12 months</span>
                            </div>
                            <div className="relative mt-4 h-28 overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-900/70">
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                            </div>
                        </div>
                    </section>

                    <aside className="flex flex-col gap-6">
                        <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.6)] backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                            <div className="flex items-center justify-between">
                                <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                    Signal board
                                </h2>
                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                    Live
                                </span>
                            </div>
                            <div className="mt-4 grid gap-4">
                                {signals.map((signal) => (
                                    <div
                                        key={signal.title}
                                        className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/70"
                                    >
                                        <span
                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${signal.tone}`}
                                        >
                                            {signal.label}
                                        </span>
                                        <h3 className="mt-3 text-sm font-semibold text-slate-900 dark:text-white">
                                            {signal.title}
                                        </h3>
                                        <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                            {signal.detail}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                            <div className="flex items-center justify-between">
                                <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                    Watchlist moves
                                </h2>
                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                    Today
                                </span>
                            </div>
                            <div className="mt-4 grid gap-4">
                                {watchlistMoves.map((item) => (
                                    <div
                                        key={item.symbol}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <div className="flex flex-col">
                                            <span className="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                                                {item.symbol}
                                            </span>
                                            <span className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {item.name}
                                            </span>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {item.price}
                                            </div>
                                            <div
                                                className={`text-xs font-semibold ${
                                                    item.change.startsWith('+')
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-rose-600 dark:text-rose-400'
                                                }`}
                                            >
                                                {item.change}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </aside>
                </div>

                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                        <div className="flex items-center justify-between">
                            <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                Portfolio truth
                            </h2>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                128 positions
                            </span>
                        </div>
                        <div className="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                            <div className="flex items-center justify-between">
                                <span>Equities</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    62%
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Funds + ETFs</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    23%
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Cash + alternatives</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    15%
                                </span>
                            </div>
                        </div>
                        <div className="relative mt-4 h-20 overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-900/70">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </div>

                    <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                        <div className="flex items-center justify-between">
                            <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                Expense tracker
                            </h2>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                Monthly
                            </span>
                        </div>
                        <div className="mt-4 grid gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <div className="flex items-center justify-between">
                                <span>Housing</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    $2.4k
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Discretionary</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    $1.1k
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Subscriptions</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    $420
                                </span>
                            </div>
                        </div>
                        <div className="mt-4 rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 text-xs text-slate-600 dark:border-slate-800/70 dark:bg-slate-950/60 dark:text-slate-300">
                            Budget drift: +4.6% vs plan
                        </div>
                    </div>

                    <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                        <div className="flex items-center justify-between">
                            <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                Debt + liquidity
                            </h2>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                Stable
                            </span>
                        </div>
                        <div className="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                            <div className="flex items-center justify-between">
                                <span>Loans</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    $184k
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Credit utilization</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    18%
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Cash buffer</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    6.2 months
                                </span>
                            </div>
                        </div>
                        <div className="mt-4 rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 text-xs text-slate-600 dark:border-slate-800/70 dark:bg-slate-950/60 dark:text-slate-300">
                            Rate watch: refinance window opens at 6.5%
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
                    <section className="relative overflow-hidden rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 shadow-[0_30px_90px_-70px_rgba(15,23,42,0.7)] backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                    Portfolio analytics
                                </h2>
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    Allocation, risk drift, and time-series
                                    performance across USD and INR views.
                                </p>
                            </div>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                Last 90 days
                            </span>
                        </div>
                        <div className="relative mt-6 h-56 overflow-hidden rounded-2xl border border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-900/70">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </section>

                    <aside className="flex flex-col gap-6">
                        <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                            <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                Reporting cadence
                            </h2>
                            <div className="mt-4 grid gap-4">
                                {cadence.map((item) => (
                                    <div
                                        key={item.title}
                                        className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/70"
                                    >
                                        <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                            <span>{item.title}</span>
                                            <span>{item.timing}</span>
                                        </div>
                                        <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                            {item.detail}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="rounded-3xl border border-sidebar-border/70 bg-white/80 p-6 backdrop-blur dark:border-sidebar-border dark:bg-slate-900/70">
                            <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                                Research feed
                            </h2>
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                Curated strategies, tax tips, and market
                                insights personalized to your holdings.
                            </p>
                            <div className="relative mt-4 h-24 overflow-hidden rounded-2xl border border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-900/70">
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
