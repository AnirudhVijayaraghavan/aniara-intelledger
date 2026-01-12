import { login, register } from '@/routes';
import { Head, Link } from '@inertiajs/react';

type WelcomeProps = {
    canRegister?: boolean;
};

type IconProps = {
    className?: string;
};

type SectionHeaderProps = {
    eyebrow: string;
    title: string;
    description: string;
    align?: 'left' | 'center';
};

type Pillar = {
    title: string;
    description: string;
    icon: (props: IconProps) => JSX.Element;
};

type Step = {
    title: string;
    description: string;
};

type Report = {
    title: string;
    cadence: string;
    description: string;
};

type FeedItem = {
    label: string;
    title: string;
    description: string;
};

type Metric = {
    label: string;
    value: string;
    detail: string;
};

type WatchItem = {
    symbol: string;
    name: string;
    change: string;
    direction: 'up' | 'down';
};

type Guardrail = {
    title: string;
    description: string;
};

function IconPortfolio({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M4 7h16M6 7V5a1 1 0 0 1 1-1h3M18 7V5a1 1 0 0 0-1-1h-3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <rect
                x={3}
                y={7}
                width={18}
                height={12}
                rx={2}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M9 11h6M9 15h4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconNetWorth({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M4 17c2-4 6-8 10-8s6 4 6 8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M4 7h16"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M8 7V5M16 7V5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconBriefing({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M6 4h9l3 3v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M15 4v4h4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M8 12h8M8 16h6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconCashflow({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M4 18h16"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M7 15V9M12 15V6M17 15v-4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconBell({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M12 5c-3 0-5 2-5 5v3l-2 2h14l-2-2v-3c0-3-2-5-5-5Z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M10 19a2 2 0 0 0 4 0"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconShield({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconCheck({ className }: IconProps) {
    return (
        <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M4.5 10.5l3.5 3.5 7-7"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function MiniChart({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 300 90"
            className={className}
            preserveAspectRatio="none"
            aria-hidden="true"
            focusable="false"
        >
            <defs>
                <linearGradient
                    id="chartGradient"
                    x1="0"
                    y1="0"
                    x2="1"
                    y2="0"
                >
                    <stop offset="0%" stopColor="rgba(16, 185, 129, 0.1)" />
                    <stop
                        offset="55%"
                        stopColor="rgba(14, 165, 233, 0.25)"
                    />
                    <stop offset="100%" stopColor="rgba(20, 184, 166, 0.1)" />
                </linearGradient>
                <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="rgba(16, 185, 129, 0.24)" />
                    <stop offset="100%" stopColor="rgba(16, 185, 129, 0)" />
                </linearGradient>
            </defs>
            <path
                d="M0 70 L35 60 L70 64 L110 48 L145 54 L185 36 L220 40 L255 26 L300 30 L300 90 L0 90 Z"
                fill="url(#chartFill)"
            />
            <path
                d="M0 70 L35 60 L70 64 L110 48 L145 54 L185 36 L220 40 L255 26 L300 30"
                stroke="url(#chartGradient)"
                strokeWidth="3"
                fill="none"
            />
        </svg>
    );
}

const sources = [
    'Chase',
    'ICICI',
    'Robinhood',
    'Fidelity',
    'Employer 401k',
    'Manual entry',
];

const metrics: Metric[] = [
    {
        label: 'Markets',
        value: 'US + India',
        detail: 'Brokerage, banking, retirement',
    },
    {
        label: 'Portfolio scope',
        value: 'Positions + Watchlist',
        detail: 'Equities, ETFs, funds, debt',
    },
    {
        label: 'Reporting',
        value: 'Daily / Weekly / Monthly',
        detail: 'Pre-market to month-end close',
    },
];

const pillars: Pillar[] = [
    {
        title: 'Portfolio truth',
        description:
            'Full position-level coverage across US and India, plus a live watchlist you can manage manually.',
        icon: IconPortfolio,
    },
    {
        title: 'Net worth and debt clarity',
        description:
            'Track assets, liabilities, and interest rates with time-series changes and balance sheet shifts.',
        icon: IconNetWorth,
    },
    {
        title: 'Cashflow engine',
        description:
            'Income, expenses, and budgets unified with investment performance for a true daily picture.',
        icon: IconCashflow,
    },
    {
        title: 'Strategy and alerts',
        description:
            'Robo-advisor insights, rebalancing ideas, and push alerts based on holdings and risk.',
        icon: IconBell,
    },
];

const workflowSteps: Step[] = [
    {
        title: 'Connect accounts or import manually',
        description:
            'Link Chase, ICICI, and brokerage accounts or upload CSV files for full control.',
    },
    {
        title: 'IntelLedger normalizes every position',
        description:
            'Holdings are mapped to a base currency, tagged by sector, and unified into one view.',
    },
    {
        title: 'Receive briefings, reports, and signals',
        description:
            'Daily pre-market briefings and weekly reviews keep your strategy on track.',
    },
];

const analytics: Step[] = [
    {
        title: 'Time-series for every asset',
        description:
            'Track positions, watchlist performance, and net worth with clear historical context.',
    },
    {
        title: 'Debt, cash, and liquidity tracking',
        description:
            'Monitor loans, credit utilization, and cash buffers side by side with investments.',
    },
    {
        title: 'Expense intelligence baked in',
        description:
            'Category rules, budgets, and anomaly alerts keep spending aligned to goals.',
    },
    {
        title: 'Allocation, risk, and drift analysis',
        description:
            'Understand concentration risk and receive actionable rebalancing insights.',
    },
];

const reportCadence: Report[] = [
    {
        title: 'Daily pre-market briefing',
        cadence: '06:30 ET / 17:00 IST',
        description:
            'Overnight news, portfolio movers, and watchlist alerts before the open.',
    },
    {
        title: 'Weekly allocation review',
        cadence: 'Friday close',
        description:
            'Drift, risk exposure, and sector balance with clear next-step guidance.',
    },
    {
        title: 'Monthly net worth close',
        cadence: 'Month-end',
        description:
            'Net worth change, savings rate, and cashflow summary across all accounts.',
    },
];

const feedItems: FeedItem[] = [
    {
        label: 'Strategy',
        title: 'Suggested trim: Mega-cap tech +3.1% weight',
        description:
            'Rebalance toward target allocation while maintaining growth exposure.',
    },
    {
        label: 'News',
        title: 'Overnight macro recap and market catalysts',
        description:
            'Curated feed with relevant headlines tied to your holdings and watchlist.',
    },
    {
        label: 'Alert',
        title: 'Swing-trade trigger hit on watchlist',
        description:
            'Volume spike and price break detected on your tracked positions.',
    },
];

const watchItems: WatchItem[] = [
    {
        symbol: 'AAPL',
        name: 'Apple',
        change: '+1.4%',
        direction: 'up',
    },
    {
        symbol: 'NVDA',
        name: 'Nvidia',
        change: '-0.8%',
        direction: 'down',
    },
    {
        symbol: 'RELIANCE',
        name: 'Reliance',
        change: '+0.6%',
        direction: 'up',
    },
];

const guardrails: Guardrail[] = [
    {
        title: 'Private by default',
        description:
            'Read-only connections and strict permission boundaries protect your data.',
    },
    {
        title: 'Secure consolidation',
        description:
            'Encryption for data at rest and in transit with audit-ready logs.',
    },
    {
        title: 'Transparent recommendations',
        description:
            'Every signal includes a clear rationale, not just a generic alert.',
    },
];

function SectionHeader({
    eyebrow,
    title,
    description,
    align = 'left',
}: SectionHeaderProps) {
    return (
        <div
            className={`flex max-w-3xl flex-col gap-4 ${
                align === 'center' ? 'items-center text-center' : ''
            }`}
        >
            <p className="font-['IBM_Plex_Mono',_ui-monospace] text-xs uppercase tracking-[0.3em] text-emerald-700/80 dark:text-emerald-300/80">
                {eyebrow}
            </p>
            <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                {title}
            </h2>
            <p className="text-base text-slate-600 sm:text-lg dark:text-slate-300">
                {description}
            </p>
        </div>
    );
}

function PillarCard({ title, description, icon: Icon }: Pillar) {
    return (
        <div className="flex h-full flex-col gap-4 rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.5)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                <Icon className="h-5 w-5" />
            </div>
            <div className="flex flex-col gap-2">
                <h3 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                    {title}
                </h3>
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    {description}
                </p>
            </div>
        </div>
    );
}

function StepCard({ title, description, step }: Step & { step: string }) {
    return (
        <div className="flex flex-col gap-4 rounded-2xl border border-slate-200/70 bg-white/70 p-6 shadow-[0_18px_50px_-40px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
            <div className="flex h-10 w-10 items-center justify-center rounded-full border border-emerald-500/40 text-sm font-semibold text-emerald-700 dark:border-emerald-400/40 dark:text-emerald-300">
                {step}
            </div>
            <div className="flex flex-col gap-2">
                <h3 className="font-['Space_Grotesk',_ui-sans-serif] text-lg font-semibold text-slate-900 dark:text-white">
                    {title}
                </h3>
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    {description}
                </p>
            </div>
        </div>
    );
}

function FeatureItem({ title, description }: Step) {
    return (
        <div className="flex gap-4">
            <div className="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white dark:bg-white dark:text-slate-900">
                <IconCheck className="h-4 w-4" />
            </div>
            <div className="flex flex-col gap-2">
                <h3 className="text-base font-semibold text-slate-900 dark:text-white">
                    {title}
                </h3>
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    {description}
                </p>
            </div>
        </div>
    );
}

function MetricCard({ label, value, detail }: Metric) {
    return (
        <div className="flex flex-col gap-2 rounded-2xl border border-slate-200/70 bg-white/80 p-4 backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
            <dt className="text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                {label}
            </dt>
            <dd className="text-lg font-semibold text-slate-900 dark:text-white">
                {value}
            </dd>
            <dd className="text-xs text-slate-500 dark:text-slate-400">
                {detail}
            </dd>
        </div>
    );
}

function ReportCard({ title, cadence, description }: Report) {
    return (
        <div className="flex flex-col gap-2 rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/60">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                    {title}
                </h3>
                <span className="text-xs text-slate-500 dark:text-slate-400">
                    {cadence}
                </span>
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-300">
                {description}
            </p>
        </div>
    );
}

function FeedCard({ label, title, description }: FeedItem) {
    return (
        <div className="flex flex-col gap-3 rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
            <span className="text-xs uppercase tracking-[0.24em] text-emerald-700/80 dark:text-emerald-300/80">
                {label}
            </span>
            <h3 className="text-base font-semibold text-slate-900 dark:text-white">
                {title}
            </h3>
            <p className="text-sm text-slate-600 dark:text-slate-300">
                {description}
            </p>
        </div>
    );
}

function GuardrailCard({ title, description }: Guardrail) {
    return (
        <div className="flex h-full flex-col gap-4 rounded-2xl border border-slate-200/70 bg-white/80 p-6 backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                <IconShield className="h-5 w-5" />
            </div>
            <div className="flex flex-col gap-2">
                <h3 className="text-base font-semibold text-slate-900 dark:text-white">
                    {title}
                </h3>
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    {description}
                </p>
            </div>
        </div>
    );
}

function WatchRow({ symbol, name, change, direction }: WatchItem) {
    const tone =
        direction === 'up'
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-rose-600 dark:text-rose-400';

    return (
        <div className="flex items-center justify-between text-sm text-slate-600 dark:text-slate-300">
            <div className="flex flex-col">
                <span className="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    {symbol}
                </span>
                <span className="text-sm font-semibold text-slate-900 dark:text-white">
                    {name}
                </span>
            </div>
            <span className={`text-sm font-semibold ${tone}`}>{change}</span>
        </div>
    );
}

export default function Welcome({ canRegister = true }: WelcomeProps) {
    return (
        <>
            <Head title="IntelLedger">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500&display=swap"
                    rel="stylesheet"
                />
                <meta
                    head-key="description"
                    name="description"
                    content="IntelLedger is a premium personal finance and investment intelligence platform unifying US and India portfolios, net worth, and strategy."
                />
            </Head>
            <div className="relative min-h-screen bg-slate-50 text-slate-950 dark:bg-[#05080f] dark:text-slate-100">
                <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(70rem_circle_at_top,_rgba(15,23,42,0.08),_transparent_60%)] dark:bg-[radial-gradient(70rem_circle_at_top,_rgba(16,185,129,0.12),_transparent_60%)]" />
                <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(45rem_circle_at_80%_20%,_rgba(14,165,233,0.1),_transparent_60%)] dark:bg-[radial-gradient(45rem_circle_at_20%_10%,_rgba(56,189,248,0.15),_transparent_60%)]" />
                <div className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(transparent_1px,_rgba(15,23,42,0.04)_1px)] bg-[size:28px_28px] opacity-60 dark:bg-[linear-gradient(transparent_1px,_rgba(148,163,184,0.08)_1px)]" />
                <div className="font-['IBM_Plex_Sans',_ui-sans-serif]">
                    <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 pt-8">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <span className="text-sm font-semibold">
                                    IL
                                </span>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-sm font-semibold text-slate-900 dark:text-white">
                                    IntelLedger
                                </span>
                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                    Personal finance intelligence
                                </span>
                            </div>
                        </div>
                        <nav
                            className="hidden items-center gap-8 text-sm text-slate-600 md:flex dark:text-slate-300"
                            aria-label="Primary"
                        >
                            <a
                                href="#platform"
                                className="transition hover:text-slate-900 dark:hover:text-white"
                            >
                                Platform
                            </a>
                            <a
                                href="#reports"
                                className="transition hover:text-slate-900 dark:hover:text-white"
                            >
                                Reports
                            </a>
                            <a
                                href="#intelligence"
                                className="transition hover:text-slate-900 dark:hover:text-white"
                            >
                                Intelligence
                            </a>
                            <a
                                href="#security"
                                className="transition hover:text-slate-900 dark:hover:text-white"
                            >
                                Security
                            </a>
                        </nav>
                        <div className="flex items-center gap-3">
                            <Link
                                href={login()}
                                className="inline-flex items-center justify-center rounded-full border border-transparent px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                            >
                                Log in
                            </Link>
                            {canRegister && (
                                <Link
                                    href={register()}
                                    className="inline-flex items-center justify-center rounded-full border border-slate-300/80 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-slate-900 hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-300 dark:hover:text-white"
                                >
                                    Request access
                                </Link>
                            )}
                            <a
                                href="#demo"
                                className="inline-flex items-center justify-center rounded-full border border-slate-300/80 px-4 py-2 text-sm font-medium text-slate-800 transition hover:border-slate-900 hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-300 dark:hover:text-white"
                            >
                                View demo
                            </a>
                        </div>
                    </header>
                    <main className="flex flex-col">
                        <section
                            id="platform"
                            className="mx-auto w-full max-w-6xl px-6 pb-16 pt-12 sm:pt-16"
                        >
                            <div className="grid gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                                <div className="flex flex-col gap-8">
                                    <div className="flex flex-col gap-4">
                                        <p className="font-['IBM_Plex_Mono',_ui-monospace] text-xs uppercase tracking-[0.32em] text-slate-500 dark:text-slate-400">
                                            Global finance OS
                                        </p>
                                        <h1 className="font-['Space_Grotesk',_ui-sans-serif] text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                                            One command center for net worth,
                                            portfolios, and decisions.
                                        </h1>
                                        <p className="text-base text-slate-600 sm:text-lg dark:text-slate-300">
                                            Consolidate US and India accounts,
                                            track every position and debt, and
                                            receive pre-market briefings with
                                            strategy-aware alerts.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-4">
                                        {canRegister ? (
                                            <Link
                                                href={register()}
                                                className="inline-flex items-center justify-center rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-[0_20px_50px_-30px_rgba(15,23,42,0.8)] transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                                            >
                                                Request access
                                            </Link>
                                        ) : (
                                            <Link
                                                href={login()}
                                                className="inline-flex items-center justify-center rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-[0_20px_50px_-30px_rgba(15,23,42,0.8)] transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                                            >
                                                Log in
                                            </Link>
                                        )}
                                        <a
                                            href="#reports"
                                            className="inline-flex items-center justify-center rounded-full border border-slate-300/80 px-6 py-3 text-sm font-semibold text-slate-800 transition hover:border-slate-900 hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-300 dark:hover:text-white"
                                        >
                                            View reporting
                                        </a>
                                    </div>
                                    <div className="flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-300">
                                        <span className="rounded-full border border-slate-200/70 bg-white/80 px-4 py-1 dark:border-slate-800/70 dark:bg-slate-900/70">
                                            US + India coverage
                                        </span>
                                        <span className="rounded-full border border-slate-200/70 bg-white/80 px-4 py-1 dark:border-slate-800/70 dark:bg-slate-900/70">
                                            Daily briefings
                                        </span>
                                        <span className="rounded-full border border-slate-200/70 bg-white/80 px-4 py-1 dark:border-slate-800/70 dark:bg-slate-900/70">
                                            Robo-advisor signals
                                        </span>
                                    </div>
                                </div>
                                <div
                                    id="demo"
                                    className="flex flex-col gap-6 rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-[0_35px_120px_-80px_rgba(15,23,42,0.8)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex flex-col gap-1">
                                            <span className="font-['IBM_Plex_Mono',_ui-monospace] text-xs uppercase tracking-[0.24em] text-emerald-700/80 dark:text-emerald-300/80">
                                                Pre-market briefing
                                            </span>
                                            <h3 className="font-['Space_Grotesk',_ui-sans-serif] text-base font-semibold text-slate-900 dark:text-white">
                                                Global portfolio overview
                                            </h3>
                                        </div>
                                        <span className="text-xs text-slate-500 dark:text-slate-400">
                                            Updated 06:30 ET / 17:00 IST
                                        </span>
                                    </div>
                                    <div className="grid gap-4">
                                        <div className="flex flex-col gap-3 rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 dark:border-slate-800/70 dark:bg-slate-950/60">
                                            <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                                <span>Net worth</span>
                                                <span>USD base + INR view</span>
                                            </div>
                                            <div className="flex items-end justify-between">
                                                <span className="text-3xl font-semibold text-slate-900 dark:text-white">
                                                    $1.24M
                                                </span>
                                                <span className="text-sm text-emerald-600 dark:text-emerald-400">
                                                    +3.2% MoM
                                                </span>
                                            </div>
                                            <MiniChart className="h-20 w-full text-emerald-500/70" />
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/60">
                                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                                    Portfolio truth
                                                </span>
                                                <p className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                                                    128 positions, 22 watchlist
                                                    names
                                                </p>
                                                <div className="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                                    <span>Equity drift</span>
                                                    <span>+2.1%</span>
                                                </div>
                                                <div
                                                    className="mt-2 h-2 w-full rounded-full bg-slate-200 dark:bg-slate-800"
                                                    aria-hidden="true"
                                                >
                                                    <div className="h-2 w-2/3 rounded-full bg-emerald-500/70" />
                                                </div>
                                            </div>
                                            <div className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/60">
                                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                                    Cashflow + debt
                                                </span>
                                                <p className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                                                    Monthly burn $4.2k
                                                </p>
                                                <div className="mt-3 grid gap-2">
                                                    <div
                                                        className="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-800"
                                                        aria-hidden="true"
                                                    />
                                                    <div
                                                        className="h-2 w-4/5 rounded-full bg-slate-200 dark:bg-slate-800"
                                                        aria-hidden="true"
                                                    />
                                                    <div
                                                        className="h-2 w-3/5 rounded-full bg-slate-200 dark:bg-slate-800"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-800/70 dark:bg-slate-900/60">
                                            <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                                <span>Watchlist moves</span>
                                                <span>Today</span>
                                            </div>
                                            <div className="mt-4 grid gap-3">
                                                {watchItems.map((item) => (
                                                    <WatchRow
                                                        key={item.symbol}
                                                        {...item}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/80 p-4 text-sm text-slate-600 dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
                                        <IconBriefing className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                        Daily briefings blend market news,
                                        portfolio movement, and action-ready
                                        insights.
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section className="mx-auto w-full max-w-6xl px-6 py-10">
                            <div className="flex flex-col gap-8">
                                <SectionHeader
                                    eyebrow="Trusted sources"
                                    title="Connect the accounts you already use."
                                    description="Link US and India institutions or import data manually to keep the system precise."
                                />
                                <div className="flex flex-wrap gap-3 text-sm text-slate-600 dark:text-slate-300">
                                    {sources.map((source) => (
                                        <span
                                            key={source}
                                            className="rounded-full border border-slate-200/70 bg-white/80 px-4 py-2 dark:border-slate-800/70 dark:bg-slate-900/70"
                                        >
                                            {source}
                                        </span>
                                    ))}
                                </div>
                                <dl className="grid gap-4 sm:grid-cols-3">
                                    {metrics.map((metric) => (
                                        <MetricCard
                                            key={metric.label}
                                            {...metric}
                                        />
                                    ))}
                                </dl>
                            </div>
                        </section>

                        <section
                            id="pillars"
                            className="mx-auto w-full max-w-6xl px-6 py-16"
                        >
                            <div className="flex flex-col gap-10">
                                <SectionHeader
                                    eyebrow="Platform pillars"
                                    title="Designed for full-spectrum financial clarity."
                                    description="A single system for portfolio truth, net worth intelligence, and actionable strategy."
                                />
                                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                                    {pillars.map((pillar) => (
                                        <PillarCard
                                            key={pillar.title}
                                            {...pillar}
                                        />
                                    ))}
                                </div>
                            </div>
                        </section>

                        <section
                            id="workflow"
                            className="mx-auto w-full max-w-6xl px-6 py-16"
                        >
                            <div className="flex flex-col gap-10">
                                <SectionHeader
                                    eyebrow="How it works"
                                    title="Three steps to daily financial control."
                                    description="Bring data in, let IntelLedger align it, and act with confidence every day."
                                />
                                <div className="grid gap-6 lg:grid-cols-3">
                                    {workflowSteps.map((step, index) => (
                                        <StepCard
                                            key={step.title}
                                            step={`${index + 1}`}
                                            {...step}
                                        />
                                    ))}
                                </div>
                            </div>
                        </section>
                        <section
                            id="reports"
                            className="mx-auto w-full max-w-6xl px-6 py-16"
                        >
                            <div className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                                <div className="flex flex-col gap-8">
                                    <SectionHeader
                                        eyebrow="Analytics and reporting"
                                        title="Time-series insight for every asset you own."
                                        description="Performance, cashflow, and debt analytics with automated daily, weekly, and monthly reporting."
                                    />
                                    <div className="flex flex-col gap-6">
                                        {analytics.map((feature) => (
                                            <FeatureItem
                                                key={feature.title}
                                                {...feature}
                                            />
                                        ))}
                                    </div>
                                </div>
                                <div className="flex flex-col gap-6 rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-[0_30px_90px_-70px_rgba(15,23,42,0.7)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
                                    <div className="flex items-center justify-between">
                                        <div className="flex flex-col gap-1">
                                            <span className="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                                                Reporting cadence
                                            </span>
                                            <h3 className="font-['Space_Grotesk',_ui-sans-serif] text-base font-semibold text-slate-900 dark:text-white">
                                                Automated financial reviews
                                            </h3>
                                        </div>
                                        <span className="text-xs text-emerald-700 dark:text-emerald-300">
                                            Live
                                        </span>
                                    </div>
                                    <div className="grid gap-3">
                                        {reportCadence.map((report) => (
                                            <ReportCard
                                                key={report.title}
                                                {...report}
                                            />
                                        ))}
                                    </div>
                                    <div className="rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 dark:border-slate-800/70 dark:bg-slate-950/60">
                                        <span className="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                                            Net worth timeline
                                        </span>
                                        <MiniChart className="mt-4 h-20 w-full text-emerald-500/70" />
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section
                            id="intelligence"
                            className="mx-auto w-full max-w-6xl px-6 py-16"
                        >
                            <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                                <SectionHeader
                                    eyebrow="Intelligence"
                                    title="Signals, strategies, and curated market insight."
                                    description="Automated feeds and recommendations tied to your actual holdings for better decisions."
                                />
                                <div className="grid gap-4">
                                    {feedItems.map((item) => (
                                        <FeedCard
                                            key={item.title}
                                            {...item}
                                        />
                                    ))}
                                    <div className="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/80 p-5 text-sm text-slate-600 dark:border-slate-800/70 dark:bg-slate-900/70 dark:text-slate-300">
                                        <IconBell className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                        Push alerts keep you ahead of sudden
                                        volatility and watchlist triggers.
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            id="security"
                            className="mx-auto w-full max-w-6xl px-6 py-16"
                        >
                            <div className="flex flex-col gap-10">
                                <SectionHeader
                                    eyebrow="Security"
                                    title="Built with financial-grade safeguards."
                                    description="Your data stays protected while you get the clarity you need to move quickly."
                                />
                                <div className="grid gap-6 md:grid-cols-3">
                                    {guardrails.map((guardrail) => (
                                        <GuardrailCard
                                            key={guardrail.title}
                                            {...guardrail}
                                        />
                                    ))}
                                </div>
                            </div>
                        </section>

                        <section className="mx-auto w-full max-w-6xl px-6 pb-20">
                            <div className="flex flex-col gap-6 rounded-3xl border border-slate-200/70 bg-slate-900 px-6 py-10 text-white shadow-[0_30px_80px_-60px_rgba(15,23,42,0.8)] dark:border-slate-800/70">
                                <h2 className="font-['Space_Grotesk',_ui-sans-serif] text-2xl font-semibold sm:text-3xl">
                                    Build your personal financial command
                                    center.
                                </h2>
                                <p className="text-sm text-slate-200 sm:text-base">
                                    IntelLedger brings portfolio truth, net
                                    worth intelligence, and expense tracking
                                    into one daily workflow.
                                </p>
                                <div className="flex flex-wrap items-center gap-4">
                                    {canRegister ? (
                                        <Link
                                            href={register()}
                                            className="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
                                        >
                                            Request access
                                        </Link>
                                    ) : (
                                        <Link
                                            href={login()}
                                            className="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
                                        >
                                            Log in
                                        </Link>
                                    )}
                                    <a
                                        href="#demo"
                                        className="inline-flex items-center justify-center rounded-full border border-white/40 px-6 py-3 text-sm font-semibold text-white/90 transition hover:border-white hover:text-white"
                                    >
                                        View demo
                                    </a>
                                </div>
                            </div>
                        </section>
                    </main>
                    <footer className="border-t border-slate-200/70 dark:border-slate-800/70">
                        <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-6 py-10 text-sm text-slate-500 dark:text-slate-400">
                            <span className="text-base font-semibold text-slate-900 dark:text-white">
                                IntelLedger
                            </span>
                            <span>
                                Personal finance and investment intelligence
                                for US + India markets.
                            </span>
                            <span className="text-xs">
                                Built for serious, long-term investors.
                            </span>
                        </div>
                    </footer>
                </div>
            </div>
        </>
    );
}
