import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';

const coreValues = [
    {
        title: 'Portfolio Truth',
        description:
            'Unify US + India holdings into a single, reconciled investment ledger.',
    },
    {
        title: 'Net Worth & Cashflow',
        description:
            'Track assets, debts, and liquidity with clean, daily clarity.',
    },
    {
        title: 'Strategy-Driven Insights',
        description:
            'Signals grounded in your strategy, not short-term noise.',
    },
    {
        title: 'Daily Briefings',
        description:
            'Concise updates and alerts focused on what actually moved.',
    },
];

const howItWorks = [
    {
        step: '01',
        title: 'Connect or add manually',
        description:
            'Securely link accounts or import statements to complete your ledger.',
    },
    {
        step: '02',
        title: 'IntelLedger organizes',
        description:
            'Holdings, cash, and risk are structured into a clear narrative.',
    },
    {
        step: '03',
        title: 'Receive daily intelligence',
        description:
            'Briefings, risk flags, and insights arrive right on schedule.',
    },
];

const featureHighlights = [
    'Time-series analytics across assets and cashflow.',
    'Unified dashboard for assets, debts, and cash.',
    'Investment and expense tracking in one place.',
    'Designed for daily use and long-term clarity.',
];

export default function Welcome() {
    return (
        <>
            <Head title="IntelLedger" />
            <div className="min-h-screen bg-background text-foreground">
                <div className="relative overflow-hidden font-sans">
                    <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(203,243,240,0.45),rgba(255,255,255,0.95),rgba(255,191,105,0.12))] dark:bg-[linear-gradient(135deg,rgba(15,31,30,0.95),rgba(11,22,21,1),rgba(46,196,182,0.08))]" />
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.12),transparent_55%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.18),transparent_60%)]" />

                    <header className="relative z-10 border-b border-border bg-card/85 backdrop-blur">
                        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                            <div className="flex items-center gap-4">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-[#2ec4b6] bg-[#2ec4b6] text-sm font-semibold text-white shadow-lg shadow-[#2ec4b6]/30">
                                    IL
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.35em] text-muted-foreground">
                                        IntelLedger
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Investment intelligence ledger
                                    </p>
                                </div>
                            </div>
                            <nav className="hidden items-center gap-6 text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground md:flex">
                                <a
                                    href="#demo"
                                    className="transition hover:text-foreground"
                                >
                                    Overview
                                </a>
                                <a
                                    href="#how-it-works"
                                    className="transition hover:text-foreground"
                                >
                                    Process
                                </a>
                                <a
                                    href="#features"
                                    className="transition hover:text-foreground"
                                >
                                    Highlights
                                </a>
                                <span className="h-5 w-px bg-border" />
                                <Link
                                    href={login()}
                                    className="rounded-full border border-[#2ec4b6]/60 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-foreground transition hover:border-[#2ec4b6]"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-full bg-[#ff9f1c] px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-[#0f1f1e] shadow-lg shadow-[#ff9f1c]/30 transition hover:bg-[#ffbf69]"
                                >
                                    Sign up
                                </Link>
                            </nav>
                        </div>
                    </header>

                    <main className="relative z-10">
                        <section
                            id="demo"
                            className="mx-auto grid max-w-6xl items-center gap-12 px-6 pb-20 pt-14 lg:grid-cols-[1.05fr_0.95fr] lg:pb-28 lg:pt-20"
                        >
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#ff9f1c]">
                                    Portfolio ledger
                                </p>
                                <h1 className="mt-4 text-4xl font-semibold leading-tight sm:text-5xl">
                                    IntelLedger brings old-world discipline to
                                    modern portfolios.
                                </h1>
                                <p className="mt-6 text-lg text-muted-foreground">
                                    Consolidate US + India holdings, monitor
                                    net worth, and surface the signals that
                                    matter most. A clear ledger for serious
                                    investors who value measured decisions.
                                </p>
                                <div className="mt-8 flex flex-wrap gap-4">
                                    <a
                                        href="#demo"
                                        className="rounded-full bg-[#ff9f1c] px-6 py-3 text-sm font-semibold text-[#0f1f1e] shadow-xl shadow-[#ff9f1c]/30 transition hover:-translate-y-0.5 hover:bg-[#ffbf69]"
                                    >
                                        View Demo
                                    </a>
                                    <a
                                        href="#how-it-works"
                                        className="rounded-full border border-[#2ec4b6]/60 px-6 py-3 text-sm font-semibold text-foreground transition hover:-translate-y-0.5 hover:border-[#2ec4b6]"
                                    >
                                        Learn More
                                    </a>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-6 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-[#2ec4b6]" />
                                        <span>US + India coverage</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-[#ff9f1c]" />
                                        <span>Multi-currency clarity</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-[#ffbf69]" />
                                        <span>Daily briefings</span>
                                    </div>
                                </div>
                            </div>

                            <div className="relative">
                                <div className="rounded-3xl border border-border bg-card/95 p-6 shadow-2xl shadow-[#2ec4b6]/10 backdrop-blur">
                                    <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">
                                        <span>Ledger Summary</span>
                                        <span className="rounded-full border border-[#ff9f1c]/60 bg-[#ffbf69]/70 px-3 py-1 text-[#0f1f1e]">
                                            Daily Brief
                                        </span>
                                    </div>
                                    <div className="mt-6 divide-y divide-border text-sm">
                                        {[
                                            {
                                                label: 'Net worth',
                                                value: '$1.84M',
                                                note: 'Stable +2.1%',
                                            },
                                            {
                                                label: 'Equity exposure',
                                                value: '58%',
                                                note: 'Aligned to plan',
                                            },
                                            {
                                                label: 'Cash position',
                                                value: '$162k',
                                                note: 'Healthy buffer',
                                            },
                                        ].map((row) => (
                                            <div
                                                key={row.label}
                                                className="flex items-center justify-between py-4"
                                            >
                                                <div>
                                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                        {row.label}
                                                    </p>
                                                    <p className="mt-1 text-lg font-semibold">
                                                        {row.value}
                                                    </p>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {row.note}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 rounded-2xl border border-dashed border-[#2ec4b6]/40 bg-[#cbf3f0]/40 p-4">
                                        <p className="text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                            Signal
                                        </p>
                                        <p className="mt-2 text-sm">
                                            Exposure drift remains minimal.
                                            Rebalance not required.
                                        </p>
                                    </div>
                                </div>
                                <div className="pointer-events-none absolute -bottom-10 -left-6 h-32 w-32 rounded-full bg-[#2ec4b6]/25 blur-3xl dark:bg-[#ffbf69]/20" />
                            </div>
                        </section>

                        <section className="mx-auto max-w-6xl px-6 pb-16">
                            <div className="flex flex-col gap-4">
                                <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#ff9f1c]">
                                    Core value
                                </p>
                                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                    <div>
                                        <h2 className="text-2xl font-semibold">
                                            Intelligence with the discipline of
                                            a ledger.
                                        </h2>
                                        <p className="mt-3 max-w-2xl text-base text-muted-foreground">
                                            Each insight is grounded in your
                                            full financial picture, recorded
                                            with the precision a long-term
                                            investor expects.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                {coreValues.map((value) => (
                                    <div
                                        key={value.title}
                                        className="group rounded-2xl border border-border bg-card/90 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                                    >
                                        <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#2ec4b6]/40 bg-[#cbf3f0]/60 text-[#2ec4b6]">
                                            <span className="text-sm font-semibold">
                                                {value.title.slice(0, 1)}
                                            </span>
                                        </div>
                                        <h3 className="mt-4 text-base font-semibold">
                                            {value.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {value.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section
                            id="how-it-works"
                            className="mx-auto max-w-6xl px-6 py-16"
                        >
                            <div className="rounded-3xl border border-border bg-card/85 p-8 shadow-sm backdrop-blur lg:p-12">
                                <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.35em] text-muted-foreground">
                                            How it works
                                        </p>
                                        <h2 className="mt-3 text-2xl font-semibold">
                                            A clear process from records to
                                            decisions.
                                        </h2>
                                        <p className="mt-3 max-w-2xl text-base text-muted-foreground">
                                            IntelLedger keeps the workflow
                                            structured while giving you a daily
                                            reading on your portfolio.
                                        </p>
                                    </div>
                                    <a
                                        href="#demo"
                                        className="inline-flex items-center justify-center rounded-full border border-[#2ec4b6]/60 px-5 py-2 text-sm font-semibold text-foreground transition hover:-translate-y-0.5 hover:border-[#2ec4b6]"
                                    >
                                        View Demo
                                    </a>
                                </div>
                                <ol className="mt-10 grid gap-6 md:grid-cols-3">
                                    {howItWorks.map((item) => (
                                        <li
                                            key={item.step}
                                            className="rounded-2xl border border-border bg-card/80 p-6"
                                        >
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff9f1c]">
                                                {item.step}
                                            </p>
                                            <h3 className="mt-3 text-lg font-semibold">
                                                {item.title}
                                            </h3>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {item.description}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </section>

                        <section
                            id="features"
                            className="mx-auto max-w-6xl px-6 pb-20"
                        >
                            <div className="grid gap-12 lg:grid-cols-[0.95fr_1.05fr]">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#ff9f1c]">
                                        Feature highlights
                                    </p>
                                    <h2 className="mt-3 text-2xl font-semibold">
                                        Crafted for daily review, not quarterly
                                        catch-up.
                                    </h2>
                                    <p className="mt-4 text-base text-muted-foreground">
                                        IntelLedger turns complex portfolios
                                        into a dependable dashboard you can
                                        consult each morning.
                                    </p>
                                    <ul className="mt-6 space-y-3 text-sm text-muted-foreground">
                                        {featureHighlights.map((feature) => (
                                            <li
                                                key={feature}
                                                className="flex items-start gap-3"
                                            >
                                                <span className="mt-1 h-2 w-2 rounded-full bg-[#2ec4b6]" />
                                                <span>{feature}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                                <div className="rounded-3xl border border-border bg-card/90 p-6 shadow-lg shadow-[#2ec4b6]/10">
                                    <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">
                                        <span>Unified dashboard</span>
                                        <span className="rounded-full border border-[#2ec4b6]/40 bg-[#cbf3f0]/70 px-3 py-1 text-[#0f1f1e]">
                                            Daily view
                                        </span>
                                    </div>
                                    <div className="mt-6 grid gap-4">
                                        <div className="rounded-2xl border border-border bg-[#cbf3f0]/35 p-4">
                                            <p className="text-sm font-semibold">
                                                Assets, debts, cash
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                All sources in one ledger view
                                            </p>
                                            <div className="mt-4 grid grid-cols-3 gap-3">
                                                <div className="h-16 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-card/80" />
                                                <div className="h-16 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-card/80" />
                                                <div className="h-16 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-card/80" />
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-border p-4">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-semibold">
                                                    Time-series analytics
                                                </p>
                                                <span className="text-xs text-[#2ec4b6]">
                                                    Rolling 12 months
                                                </span>
                                            </div>
                                            <div className="mt-3 h-24 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-gradient-to-r from-[#cbf3f0]/70 to-transparent" />
                                            <div className="mt-3 flex justify-between text-xs text-muted-foreground">
                                                <span>Volatility lens</span>
                                                <span>Cashflow sync</span>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-border p-4">
                                            <p className="text-sm font-semibold">
                                                Strategy-ready reporting
                                            </p>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Export-ready summaries for
                                                advisors and personal records.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>

                    <footer className="border-t border-border bg-card/80">
                        <div className="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-10 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-sm font-semibold">
                                    IntelLedger
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Personal finance and investment intelligence
                                    for serious investors.
                                </p>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Built for US + India portfolios.
                            </p>
                        </div>
                    </footer>
                </div>
            </div>
        </>
    );
}
