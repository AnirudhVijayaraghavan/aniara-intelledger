import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

const coreValues = [
    {
        title: 'Portfolio Truth',
        description:
            'Unify US and India holdings into a single, reconciled investment ledger.',
    },
    {
        title: 'Net Worth and Cash Flow',
        description:
            'Track assets, liabilities, and liquidity with a cleaner daily view.',
    },
    {
        title: 'Strategy-Driven Signals',
        description:
            'Keep research and portfolio context tied to your actual allocation decisions.',
    },
    {
        title: 'Daily Briefings',
        description:
            'Short updates focused on changes that are worth reviewing.',
    },
];

const howItWorks = [
    {
        step: '01',
        title: 'Connect or add manually',
        description:
            'Link accounts or import records to complete the ledger.',
    },
    {
        step: '02',
        title: 'IntelLedger structures it',
        description:
            'Holdings, cash, and obligations are organized into one readable system.',
    },
    {
        step: '03',
        title: 'Review the daily brief',
        description:
            'A single morning view for portfolio, liquidity, and research.',
    },
];

const featureHighlights = [
    'Time-series analytics across assets and cash flow.',
    'Unified view for assets, liabilities, and liquidity.',
    'Portfolio and expense tracking in one place.',
    'Designed for daily use and long-term clarity.',
];

const summaryRows = [
    {
        label: 'Coverage',
        value: 'US + India',
        note: 'Multi-currency',
    },
    {
        label: 'View',
        value: 'Net worth + cash flow',
        note: 'Single ledger',
    },
    {
        label: 'Routine',
        value: 'Daily briefings',
        note: 'Low-noise review',
    },
];

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Aniara IntelLedger">
                <meta
                    name="description"
                    content="A portfolio intelligence ledger for cross-border investing, net worth tracking, expense review, and daily portfolio briefings."
                />
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=fraunces:500,600,700|public-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-white text-[#0f1f1e] dark:bg-[#0f1f1e] dark:text-[#f3fffd]">
                <div
                    className="relative overflow-hidden"
                    style={{
                        fontFamily:
                            "'Public Sans', ui-sans-serif, system-ui, sans-serif",
                    }}
                >
                    <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(203,243,240,0.55),rgba(255,255,255,0.96),rgba(255,191,105,0.12))] dark:bg-[linear-gradient(135deg,rgba(15,31,30,0.98),rgba(11,22,21,1),rgba(46,196,182,0.10))]" />
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.14),transparent_55%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.20),transparent_60%)]" />

                    <header className="relative z-10 border-b border-[#0f1f1e]/10 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-[#102220]/85">
                        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-5 px-6 py-6">
                            <div className="flex items-center gap-4">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-[#2ec4b6] bg-[#2ec4b6] text-sm font-semibold text-white shadow-lg shadow-[#2ec4b6]/30">
                                    IL
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#5c7875] dark:text-[#95b9b5]">
                                        IntelLedger
                                    </p>
                                    <p className="text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                        Investment intelligence ledger
                                    </p>
                                </div>
                            </div>

                            <nav className="hidden items-center gap-6 text-xs font-semibold uppercase tracking-[0.3em] text-[#5c7875] md:flex dark:text-[#95b9b5]">
                                <a
                                    href="#overview"
                                    className="transition hover:text-[#0f1f1e] dark:hover:text-white"
                                >
                                    Overview
                                </a>
                                <a
                                    href="#process"
                                    className="transition hover:text-[#0f1f1e] dark:hover:text-white"
                                >
                                    Process
                                </a>
                                <a
                                    href="#highlights"
                                    className="transition hover:text-[#0f1f1e] dark:hover:text-white"
                                >
                                    Highlights
                                </a>
                                <span className="h-5 w-px bg-[#0f1f1e]/12 dark:bg-white/12" />
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        prefetch
                                        className="rounded-full bg-[#ff9f1c] px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-[#0f1f1e] shadow-lg shadow-[#ff9f1c]/25 transition hover:bg-[#ffbf69]"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            prefetch
                                            className="rounded-full border border-[#2ec4b6]/60 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-[#0f1f1e] transition hover:border-[#2ec4b6] dark:text-white"
                                        >
                                            Log in
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={register()}
                                                prefetch
                                                className="rounded-full bg-[#ff9f1c] px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-[#0f1f1e] shadow-lg shadow-[#ff9f1c]/25 transition hover:bg-[#ffbf69]"
                                            >
                                                Sign up
                                            </Link>
                                        )}
                                    </>
                                )}
                            </nav>

                            <div className="flex items-center gap-3 md:hidden">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        prefetch
                                        className="rounded-full bg-[#ff9f1c] px-4 py-2 text-xs font-semibold text-[#0f1f1e]"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            prefetch
                                            className="rounded-full border border-[#2ec4b6]/60 px-4 py-2 text-xs font-semibold"
                                        >
                                            Log in
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={register()}
                                                prefetch
                                                className="rounded-full bg-[#ff9f1c] px-4 py-2 text-xs font-semibold text-[#0f1f1e]"
                                            >
                                                Sign up
                                            </Link>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </header>

                    <main className="relative z-10">
                        <section
                            id="overview"
                            className="mx-auto grid max-w-6xl items-center gap-12 px-6 pb-20 pt-14 lg:grid-cols-[1.05fr_0.95fr] lg:pb-28 lg:pt-20"
                        >
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#ff9f1c]">
                                    Portfolio ledger
                                </p>
                                <h1
                                    className="mt-4 text-4xl font-semibold leading-tight sm:text-5xl"
                                    style={{
                                        fontFamily:
                                            "'Fraunces', ui-serif, Georgia, serif",
                                    }}
                                >
                                    IntelLedger brings measured structure to modern portfolios.
                                </h1>
                                <p className="mt-6 max-w-2xl text-lg text-[#5c7875] dark:text-[#95b9b5]">
                                    Consolidate US and India holdings, review net worth, and keep a cleaner daily read on
                                    liquidity and research.
                                </p>
                                <div className="mt-8 flex flex-wrap gap-4">
                                    <a
                                        href="#overview"
                                        className="rounded-full bg-[#ff9f1c] px-6 py-3 text-sm font-semibold text-[#0f1f1e] shadow-xl shadow-[#ff9f1c]/25 transition hover:-translate-y-0.5 hover:bg-[#ffbf69]"
                                    >
                                        View overview
                                    </a>
                                    <a
                                        href="#process"
                                        className="rounded-full border border-[#2ec4b6]/60 px-6 py-3 text-sm font-semibold text-[#0f1f1e] transition hover:-translate-y-0.5 hover:border-[#2ec4b6] dark:text-white"
                                    >
                                        How it works
                                    </a>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-6 text-sm text-[#5c7875] dark:text-[#95b9b5]">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-[#2ec4b6]" />
                                        <span>US and India coverage</span>
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
                                <div className="rounded-3xl border border-[#0f1f1e]/10 bg-white/95 p-6 shadow-2xl shadow-[#2ec4b6]/10 backdrop-blur dark:border-white/10 dark:bg-[#132725]/92">
                                    <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-[#5c7875] dark:text-[#95b9b5]">
                                        <span>Ledger summary</span>
                                        <span className="rounded-full border border-[#ff9f1c]/60 bg-[#ffbf69]/70 px-3 py-1 text-[#0f1f1e]">
                                            Daily brief
                                        </span>
                                    </div>
                                    <div className="mt-6 divide-y divide-[#0f1f1e]/8 text-sm dark:divide-white/8">
                                        {summaryRows.map((row) => (
                                            <div
                                                key={row.label}
                                                className="flex items-center justify-between py-4"
                                            >
                                                <div>
                                                    <p className="text-xs uppercase tracking-[0.2em] text-[#5c7875] dark:text-[#95b9b5]">
                                                        {row.label}
                                                    </p>
                                                    <p className="mt-1 text-lg font-semibold">
                                                        {row.value}
                                                    </p>
                                                </div>
                                                <p className="text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                                    {row.note}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 rounded-2xl border border-dashed border-[#2ec4b6]/40 bg-[#cbf3f0]/40 p-4 dark:bg-[#173230]">
                                        <p className="text-xs uppercase tracking-[0.3em] text-[#5c7875] dark:text-[#95b9b5]">
                                            Signal
                                        </p>
                                        <p className="mt-2 text-sm">
                                            One place for portfolio review, cash position, and research follow-up.
                                        </p>
                                    </div>
                                </div>
                                <div className="pointer-events-none absolute -bottom-10 -left-6 h-32 w-32 rounded-full bg-[#2ec4b6]/25 blur-3xl dark:bg-[#ffbf69]/16" />
                            </div>
                        </section>

                        <section className="mx-auto max-w-6xl px-6 pb-16">
                            <div className="flex flex-col gap-4">
                                <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#ff9f1c]">
                                    Core value
                                </p>
                                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                    <div>
                                        <h2
                                            className="text-2xl font-semibold"
                                            style={{
                                                fontFamily:
                                                    "'Fraunces', ui-serif, Georgia, serif",
                                            }}
                                        >
                                            Intelligence with the discipline of a ledger.
                                        </h2>
                                        <p className="mt-3 max-w-2xl text-base text-[#5c7875] dark:text-[#95b9b5]">
                                            Each view stays anchored to the full financial picture rather than isolated
                                            accounts or noisy market moves.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                {coreValues.map((value) => (
                                    <div
                                        key={value.title}
                                        className="group rounded-2xl border border-[#0f1f1e]/10 bg-white/90 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-[#132725]/90"
                                    >
                                        <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#2ec4b6]/40 bg-[#cbf3f0]/60 text-[#2ec4b6]">
                                            <span className="text-sm font-semibold">
                                                {value.title.slice(0, 1)}
                                            </span>
                                        </div>
                                        <h3 className="mt-4 text-base font-semibold">
                                            {value.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-[#5c7875] dark:text-[#95b9b5]">
                                            {value.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section
                            id="process"
                            className="mx-auto max-w-6xl px-6 py-16"
                        >
                            <div className="rounded-3xl border border-[#0f1f1e]/10 bg-white/88 p-8 shadow-sm backdrop-blur lg:p-12 dark:border-white/10 dark:bg-[#132725]/88">
                                <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#5c7875] dark:text-[#95b9b5]">
                                            How it works
                                        </p>
                                        <h2
                                            className="mt-3 text-2xl font-semibold"
                                            style={{
                                                fontFamily:
                                                    "'Fraunces', ui-serif, Georgia, serif",
                                            }}
                                        >
                                            A clear process from records to daily review.
                                        </h2>
                                        <p className="mt-3 max-w-2xl text-base text-[#5c7875] dark:text-[#95b9b5]">
                                            The workflow stays simple: structure the records, then read the resulting
                                            picture every day.
                                        </p>
                                    </div>
                                    <a
                                        href="#overview"
                                        className="inline-flex items-center justify-center rounded-full border border-[#2ec4b6]/60 px-5 py-2 text-sm font-semibold text-[#0f1f1e] transition hover:-translate-y-0.5 hover:border-[#2ec4b6] dark:text-white"
                                    >
                                        View overview
                                    </a>
                                </div>
                                <ol className="mt-10 grid gap-6 md:grid-cols-3">
                                    {howItWorks.map((item) => (
                                        <li
                                            key={item.step}
                                            className="rounded-2xl border border-[#0f1f1e]/10 bg-white/80 p-6 dark:border-white/10 dark:bg-[#17302d]/80"
                                        >
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff9f1c]">
                                                {item.step}
                                            </p>
                                            <h3 className="mt-3 text-lg font-semibold">
                                                {item.title}
                                            </h3>
                                            <p className="mt-2 text-sm text-[#5c7875] dark:text-[#95b9b5]">
                                                {item.description}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </section>

                        <section
                            id="highlights"
                            className="mx-auto max-w-6xl px-6 pb-20"
                        >
                            <div className="grid gap-12 lg:grid-cols-[0.95fr_1.05fr]">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#ff9f1c]">
                                        Feature highlights
                                    </p>
                                    <h2
                                        className="mt-3 text-2xl font-semibold"
                                        style={{
                                            fontFamily:
                                                "'Fraunces', ui-serif, Georgia, serif",
                                        }}
                                    >
                                        Built for repeatable daily review.
                                    </h2>
                                    <p className="mt-4 text-base text-[#5c7875] dark:text-[#95b9b5]">
                                        The product is meant to be checked often without turning into a noisy terminal.
                                    </p>
                                    <ul className="mt-6 space-y-3 text-sm text-[#5c7875] dark:text-[#95b9b5]">
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
                                <div className="rounded-3xl border border-[#0f1f1e]/10 bg-white/92 p-6 shadow-lg shadow-[#2ec4b6]/10 dark:border-white/10 dark:bg-[#132725]/92">
                                    <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-[#5c7875] dark:text-[#95b9b5]">
                                        <span>Unified dashboard</span>
                                        <span className="rounded-full border border-[#2ec4b6]/40 bg-[#cbf3f0]/70 px-3 py-1 text-[#0f1f1e]">
                                            Daily view
                                        </span>
                                    </div>
                                    <div className="mt-6 grid gap-4">
                                        <div className="rounded-2xl border border-[#0f1f1e]/10 bg-[#cbf3f0]/35 p-4 dark:border-white/10 dark:bg-[#173230]">
                                            <p className="text-sm font-semibold">
                                                Assets, liabilities, cash
                                            </p>
                                            <p className="text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                                One ledger view across holdings and liquidity
                                            </p>
                                            <div className="mt-4 grid grid-cols-3 gap-3">
                                                <div className="h-16 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-white/80 dark:bg-[#132725]" />
                                                <div className="h-16 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-white/80 dark:bg-[#132725]" />
                                                <div className="h-16 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-white/80 dark:bg-[#132725]" />
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-[#0f1f1e]/10 p-4 dark:border-white/10">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-semibold">
                                                    Time-series analytics
                                                </p>
                                                <span className="text-xs text-[#2ec4b6]">
                                                    Rolling view
                                                </span>
                                            </div>
                                            <div className="mt-3 h-24 rounded-xl border border-dashed border-[#2ec4b6]/40 bg-gradient-to-r from-[#cbf3f0]/70 to-transparent dark:from-[#173230]" />
                                            <div className="mt-3 flex justify-between text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                                <span>Portfolio lens</span>
                                                <span>Cash flow lens</span>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-[#0f1f1e]/10 p-4 dark:border-white/10">
                                            <p className="text-sm font-semibold">
                                                Strategy-ready reporting
                                            </p>
                                            <p className="mt-2 text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                                A cleaner surface for daily records and long-term review.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>

                    <footer className="border-t border-[#0f1f1e]/10 bg-white/80 dark:border-white/10 dark:bg-[#102220]/80">
                        <div className="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-10 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-sm font-semibold">
                                    IntelLedger
                                </p>
                                <p className="text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                    Personal finance and investment intelligence for cross-border portfolios.
                                </p>
                            </div>
                            <p className="text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                Built for US and India portfolios.
                            </p>
                        </div>
                    </footer>
                </div>
            </div>
        </>
    );
}
