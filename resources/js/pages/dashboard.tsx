import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                <div className="flex flex-col gap-2">
                    <p className="text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">
                        IntelLedger dashboard
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Portfolio overview
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Placeholder data for layout and information density.
                    </p>
                </div>

                <section className="grid gap-4 md:grid-cols-4">
                    {[
                        {
                            label: 'Net worth',
                            value: '$1.84M',
                            note: '+2.1% MTD',
                        },
                        {
                            label: 'Cash position',
                            value: '$162k',
                            note: 'Healthy buffer',
                        },
                        {
                            label: 'Equity exposure',
                            value: '58%',
                            note: 'Aligned to plan',
                        },
                        {
                            label: 'Daily signal',
                            value: 'Stable',
                            note: 'No rebalance',
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-2xl border border-border bg-card/90 p-4"
                        >
                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                {stat.label}
                            </p>
                            <p className="mt-2 text-xl font-semibold text-foreground">
                                {stat.value}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {stat.note}
                            </p>
                        </div>
                    ))}
                </section>

                <section className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <div className="rounded-2xl border border-border bg-card/90 p-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Net worth timeline
                                </p>
                                <p className="text-base font-semibold">
                                    Rolling 12 months
                                </p>
                            </div>
                            <span className="rounded-full border border-[#2ec4b6]/40 bg-[#cbf3f0]/70 px-3 py-1 text-xs font-semibold text-[#0f1f1e]">
                                Placeholder chart
                            </span>
                        </div>
                        <div className="mt-4 h-48 rounded-2xl border border-dashed border-[#2ec4b6]/40 bg-[#cbf3f0]/35" />
                        <div className="mt-4 flex flex-wrap gap-4 text-xs text-muted-foreground">
                            <span>Volatility lens</span>
                            <span>Cashflow sync</span>
                            <span>Risk overlay</span>
                        </div>
                    </div>
                    <div className="rounded-2xl border border-border bg-card/90 p-5">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Strategy signals
                        </p>
                        <ul className="mt-4 space-y-3 text-sm">
                            {[
                                'Exposure drift within limits',
                                'Cash reserves remain above target',
                                'Earnings calendar monitored',
                                'FX impact neutral this week',
                            ].map((signal) => (
                                <li
                                    key={signal}
                                    className="flex items-center justify-between"
                                >
                                    <span>{signal}</span>
                                    <span className="text-xs text-muted-foreground">
                                        Stable
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-[1.3fr_1fr]">
                    <div className="rounded-2xl border border-border bg-card/90 p-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Holdings ledger
                                </p>
                                <p className="text-base font-semibold">
                                    Top positions (placeholder)
                                </p>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Updated today
                            </span>
                        </div>
                        <div className="mt-4 divide-y divide-border text-sm">
                            {[
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
                                    name: 'Cash & Short-term',
                                    value: '$162k',
                                    change: 'Stable',
                                },
                            ].map((position) => (
                                <div
                                    key={position.name}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <p className="font-medium text-foreground">
                                            {position.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {position.value}
                                        </p>
                                    </div>
                                    <span className="text-xs font-semibold text-[#2ec4b6]">
                                        {position.change}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-2xl border border-border bg-card/90 p-5">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Cashflow intelligence
                        </p>
                        <div className="mt-4 grid gap-4">
                            <div className="rounded-2xl border border-dashed border-[#2ec4b6]/40 bg-[#cbf3f0]/35 p-4">
                                <p className="text-sm font-semibold">
                                    Monthly inflow
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    $24.6k (placeholder)
                                </p>
                            </div>
                            <div className="rounded-2xl border border-dashed border-[#2ec4b6]/40 bg-[#cbf3f0]/35 p-4">
                                <p className="text-sm font-semibold">
                                    Monthly outflow
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    $18.2k (placeholder)
                                </p>
                            </div>
                            <div className="rounded-2xl border border-dashed border-[#2ec4b6]/40 bg-[#cbf3f0]/35 p-4">
                                <p className="text-sm font-semibold">
                                    Net buffer
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    $6.4k (placeholder)
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
