import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

const overviewCards = [
    {
        label: 'Net worth',
        value: '$1.84M',
        note: '+2.1% month to date',
        accent: 'bg-intel-mint',
        tone: 'bg-intel-mint-soft',
    },
    {
        label: 'Cash position',
        value: '$162k',
        note: 'Healthy operating buffer',
        accent: 'bg-intel-amber',
        tone: 'bg-intel-amber-soft',
    },
    {
        label: 'Equity exposure',
        value: '58%',
        note: 'Within current range',
        accent: 'bg-intel-mint',
        tone: 'bg-intel-mint-soft',
    },
    {
        label: 'Daily signal',
        value: 'Stable',
        note: 'No rebalance required',
        accent: 'bg-intel-amber',
        tone: 'bg-intel-amber-soft',
    },
] as const;

const strategySignals = [
    'Exposure drift remains within portfolio limits',
    'Cash reserves stay above short-term target',
    'Upcoming earnings events are already flagged',
    'Cross-border FX impact remains contained',
] as const;

const topPositions = [
    {
        name: 'Large Cap US Equity',
        value: '$620k',
        change: '+1.4%',
    },
    {
        name: 'India Growth Fund',
        value: '$340k',
        change: '+0.9%',
    },
    {
        name: 'Global Fixed Income',
        value: '$210k',
        change: '+0.4%',
    },
    {
        name: 'Cash and Short-term',
        value: '$162k',
        change: 'Stable',
    },
] as const;

const cashflowCards = [
    {
        label: 'Monthly inflow',
        value: '$24.6k',
        tone: 'bg-intel-mint-soft',
    },
    {
        label: 'Monthly outflow',
        value: '$18.2k',
        tone: 'bg-intel-surface-muted',
    },
    {
        label: 'Net buffer',
        value: '$6.4k',
        tone: 'bg-intel-amber-soft',
    },
] as const;

const researchQueue = [
    'Review sector concentration across US equities',
    'Refresh India watchlist names after earnings',
    'Check cash drag against deployment plan',
] as const;

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=fraunces:500,600,700|public-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto bg-intel-canvas p-4 md:p-6">
                <section className="rounded-3xl border border-intel-line bg-intel-surface p-6 shadow-sm">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex flex-col gap-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">
                                IntelLedger dashboard
                            </p>
                            <h1 className="text-3xl font-semibold text-foreground [font-family:var(--font-editorial)]">
                                Portfolio overview
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                A clean operating view across holdings, liquidity,
                                and research priorities.
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                <span className="rounded-full bg-intel-mint-soft px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground">
                                    Holdings
                                </span>
                                <span className="rounded-full bg-intel-amber-soft px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground">
                                    Cash flow
                                </span>
                                <span className="rounded-full border border-intel-line bg-intel-surface-muted px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                    Daily review
                                </span>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-2xl border border-intel-line bg-intel-surface-muted px-4 py-3">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                                    Coverage
                                </p>
                                <p className="mt-2 text-sm font-medium text-foreground">
                                    US + India
                                </p>
                            </div>
                            <div className="rounded-2xl border border-intel-line bg-intel-surface-muted px-4 py-3">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                                    Base currency
                                </p>
                                <p className="mt-2 text-sm font-medium text-foreground">
                                    Multi-currency
                                </p>
                            </div>
                            <div className="rounded-2xl border border-intel-line bg-intel-surface-muted px-4 py-3">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                                    Last sync
                                </p>
                                <p className="mt-2 text-sm font-medium text-foreground">
                                    Today
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-4">
                    {overviewCards.map((stat) => (
                        <article
                            key={stat.label}
                            className="rounded-2xl border border-intel-line bg-intel-surface p-5 shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                                    {stat.label}
                                </p>
                                <span
                                    className={`h-2.5 w-2.5 rounded-full ${stat.accent}`}
                                />
                            </div>
                            <p className="mt-4 text-2xl font-semibold text-foreground">
                                {stat.value}
                            </p>
                            <div className="mt-3">
                                <span
                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold text-foreground ${stat.tone}`}
                                >
                                    {stat.note}
                                </span>
                            </div>
                        </article>
                    ))}
                </section>

                <section className="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <article className="rounded-3xl border border-intel-line bg-intel-surface p-6 shadow-sm">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                                    Net worth timeline
                                </p>
                                <h2 className="mt-2 text-lg font-semibold text-foreground">
                                    Rolling 12 months
                                </h2>
                            </div>
                            <span className="rounded-full border border-intel-line bg-intel-mint-soft px-3 py-1 text-xs font-semibold text-foreground">
                                Placeholder chart
                            </span>
                        </div>

                        <div className="mt-6 rounded-3xl border border-intel-line bg-intel-surface-muted p-5">
                            <div className="flex h-56 items-end gap-3">
                                {[32, 44, 40, 52, 48, 60, 58, 66, 62, 74, 71, 80].map(
                                    (height, index) => (
                                        <div
                                            key={index}
                                            className="flex flex-1 items-end"
                                        >
                                            <div
                                                className="w-full rounded-t-2xl bg-[linear-gradient(180deg,var(--color-intel-mint),var(--color-intel-amber))] opacity-85"
                                                style={{ height: `${height}%` }}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                            <div className="mt-4 flex justify-between text-xs text-muted-foreground">
                                <span>Apr</span>
                                <span>Jun</span>
                                <span>Aug</span>
                                <span>Oct</span>
                                <span>Dec</span>
                                <span>Mar</span>
                            </div>
                        </div>
                    </article>

                    <article className="rounded-3xl border border-intel-line bg-intel-surface p-6 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Daily brief
                        </p>
                        <h2 className="mt-2 text-lg font-semibold text-foreground">
                            Strategy signals
                        </h2>
                        <div className="mt-6 space-y-3">
                            {strategySignals.map((signal) => (
                                <div
                                    key={signal}
                                    className="rounded-2xl border border-intel-line bg-intel-surface-muted px-4 py-3"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <p className="text-sm text-foreground">
                                            {signal}
                                        </p>
                                        <span className="rounded-full bg-intel-mint-soft px-2.5 py-1 text-[11px] font-semibold text-foreground">
                                            Stable
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </article>
                </section>

                <section className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
                    <article className="rounded-3xl border border-intel-line bg-intel-surface p-6 shadow-sm">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                                    Holdings ledger
                                </p>
                                <h2 className="mt-2 text-lg font-semibold text-foreground">
                                    Largest positions
                                </h2>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Updated today
                            </span>
                        </div>

                        <div className="mt-6 overflow-hidden rounded-2xl border border-intel-line">
                            <div className="grid grid-cols-[1.8fr_1fr_0.8fr] border-b border-intel-line bg-[linear-gradient(90deg,var(--color-intel-mint-soft),var(--color-intel-surface-muted),var(--color-intel-amber-soft))] px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                                <span>Position</span>
                                <span>Value</span>
                                <span className="text-right">Change</span>
                            </div>
                            <div className="divide-y divide-intel-line">
                                {topPositions.map((position) => (
                                    <div
                                        key={position.name}
                                        className="grid grid-cols-[1.8fr_1fr_0.8fr] items-center px-4 py-4 text-sm"
                                    >
                                        <span className="font-medium text-foreground">
                                            {position.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {position.value}
                                        </span>
                                        <span className="text-right font-semibold text-intel-mint">
                                            {position.change}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </article>

                    <div className="grid gap-4">
                        <article className="rounded-3xl border border-intel-line bg-intel-surface p-6 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                                Cash flow snapshot
                            </p>
                            <div className="mt-5 grid gap-3">
                                {cashflowCards.map((item) => (
                                    <div
                                        key={item.label}
                                        className={`rounded-2xl border border-intel-line px-4 py-4 ${item.tone}`}
                                    >
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                            {item.label}
                                        </p>
                                        <p className="mt-2 text-lg font-semibold text-foreground">
                                            {item.value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </article>

                        <article className="rounded-3xl border border-intel-line bg-intel-surface p-6 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                                Research queue
                            </p>
                            <div className="mt-5 space-y-3">
                                {researchQueue.map((item) => (
                                    <div
                                        key={item}
                                        className="flex items-start gap-3 rounded-2xl border border-intel-line bg-intel-amber-soft px-4 py-3"
                                    >
                                        <span className="mt-0.5 h-8 w-1 rounded-full bg-intel-amber" />
                                        <p className="text-sm text-foreground">
                                            {item}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
    ],
};
