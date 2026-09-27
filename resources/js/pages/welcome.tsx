import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowRight,
    BookOpen,
    BrainCircuit,
    ChartNoAxesCombined,
    Compass,
    Landmark,
    Wallet,
} from 'lucide-react';
import LedgerBrand from '@/components/ledger-brand';
import LedgerPreview from '@/components/ledger-preview';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as teams } from '@/routes/teams';

const capabilities = [
    {
        number: '01',
        icon: BookOpen,
        title: 'The everyday ledger',
        detail: 'Expenses & budgets',
        copy: 'Understand what comes in, what goes out, and what stays. Bring intention to everyday spending.',
    },
    {
        number: '02',
        icon: Landmark,
        title: 'The complete picture',
        detail: 'Accounts & net worth',
        copy: 'See cash, assets, and liabilities in context. Follow the bigger story behind your balances.',
    },
    {
        number: '03',
        icon: ChartNoAxesCombined,
        title: 'The long view',
        detail: 'Investments & portfolios',
        copy: 'Keep holdings, allocation, and performance in perspective. Know where your wealth is working.',
    },
    {
        number: '04',
        icon: BrainCircuit,
        title: 'The considered decision',
        detail: 'AI & robo-advisor planning',
        copy: 'Explore a future of clearer insights and guided portfolio planning, with you in control of every decision.',
    },
];

export default function Welcome() {
    const { auth, currentTeam } = usePage().props;
    const entryUrl = auth.user
        ? currentTeam
            ? dashboard(currentTeam.slug)
            : teams()
        : register();
    const entryLabel = auth.user ? 'Open workspace' : 'Create your account';

    return (
        <>
            <Head title="Your financial operating system">
                <meta
                    name="description"
                    content="Aniara Intelledger. A considered home for your financial life: everyday expenses, wealth, portfolios, and financial intelligence."
                />
            </Head>
            <div className="ledger-public min-h-screen bg-background text-foreground">
                <header className="border-b border-border bg-card">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-6 py-6 lg:px-10">
                        <LedgerBrand />
                        <nav
                            aria-label="Main navigation"
                            className="flex items-center gap-3 sm:gap-6"
                        >
                            <a
                                href="#platform"
                                className="hidden text-sm text-muted-foreground hover:text-foreground sm:block"
                            >
                                The platform
                            </a>
                            {!auth.user && (
                                <Link
                                    href={login()}
                                    className="text-sm hover:underline"
                                >
                                    Log in
                                </Link>
                            )}
                            <Button asChild>
                                <Link href={entryUrl}>
                                    {auth.user
                                        ? 'Open workspace'
                                        : 'Get started'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </nav>
                    </div>
                </header>
                <main>
                    <section className="mx-auto grid max-w-7xl items-center gap-12 px-6 py-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16 lg:px-10 lg:py-20">
                        <div>
                            <p className="mb-7 flex items-center gap-3 text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                <span className="h-px w-8 bg-primary" /> A
                                financial operating system
                            </p>
                            <h1 className="text-5xl leading-[1.08] sm:text-6xl">
                                Your financial life.
                                <br />
                                <span className="italic">In full view.</span>
                            </h1>
                            <p className="mt-7 max-w-md text-lg leading-relaxed text-muted-foreground">
                                A single, considered home for your money. From
                                everyday expenses to lasting wealth, bring every
                                part of your financial life into perspective.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-5">
                                <Button size="lg" asChild className="h-12 px-6">
                                    <Link href={entryUrl}>
                                        {entryLabel}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                <a
                                    href="#overview"
                                    className="inline-flex items-center gap-2 text-sm underline-offset-4 hover:underline"
                                >
                                    Explore the ledger{' '}
                                    <ArrowDown className="size-4" />
                                </a>
                            </div>
                            <div className="mt-10 flex flex-wrap gap-x-6 gap-y-3 border-t border-border pt-5 text-xs text-muted-foreground">
                                <span className="flex items-center gap-2">
                                    <Wallet className="size-4" /> Everyday
                                    clarity
                                </span>
                                <span className="flex items-center gap-2">
                                    <Compass className="size-4" /> Long-term
                                    perspective
                                </span>
                            </div>
                        </div>
                        <div
                            id="overview"
                            className="min-w-0 scroll-mt-6 rounded-md bg-secondary/35 p-4 sm:p-6"
                        >
                            <div className="mb-3 flex items-center justify-between gap-2 text-xs tracking-[0.1em] text-muted-foreground uppercase">
                                <span>Inside your ledger</span>
                                <span>Product preview / 01</span>
                            </div>
                            <LedgerPreview />
                            <p className="mt-3 text-right text-xs text-muted-foreground">
                                An illustrative workspace. No live accounts
                                connected.
                            </p>
                        </div>
                    </section>
                    <section
                        id="platform"
                        className="scroll-mt-6 border-y border-border bg-secondary/20"
                    >
                        <div className="mx-auto max-w-7xl px-6 py-12 lg:px-10">
                            <div className="flex flex-wrap items-end justify-between gap-5 pb-8">
                                <div>
                                    <p className="mb-3 text-xs tracking-[0.16em] text-muted-foreground uppercase">
                                        The platform we are building
                                    </p>
                                    <h2 className="text-3xl">
                                        One place. Every financial dimension.
                                    </h2>
                                </div>
                                <span className="rounded-sm bg-accent/40 px-3 py-1.5 text-xs text-accent-foreground">
                                    Platform roadmap
                                </span>
                            </div>
                            <div className="grid gap-8 border-t border-border pt-8 sm:grid-cols-2 lg:grid-cols-4">
                                {capabilities.map(
                                    ({
                                        number,
                                        icon: Icon,
                                        title,
                                        detail,
                                        copy,
                                    }) => (
                                        <article key={number}>
                                            <div className="mb-6 flex items-center justify-between">
                                                <Icon
                                                    className="size-6"
                                                    strokeWidth={1.3}
                                                />
                                                <span className="text-xs text-muted-foreground">
                                                    /{number}
                                                </span>
                                            </div>
                                            <p className="mb-2 text-xs text-muted-foreground">
                                                {detail}
                                            </p>
                                            <h3 className="text-xl">{title}</h3>
                                            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                                                {copy}
                                            </p>
                                        </article>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                    <section className="mx-auto flex max-w-7xl flex-col justify-between gap-6 px-6 py-12 sm:flex-row sm:items-center lg:px-10">
                        <div>
                            <p className="mb-2 text-xs tracking-[0.16em] text-muted-foreground uppercase">
                                A more intentional financial life
                            </p>
                            <h2 className="text-3xl">
                                Start with a clearer perspective.
                            </h2>
                        </div>
                        <Button asChild size="lg" className="w-fit">
                            <Link href={entryUrl}>
                                {entryLabel}
                                <ArrowRight />
                            </Link>
                        </Button>
                    </section>
                </main>
                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-3 px-6 py-6 text-xs text-muted-foreground lg:px-10">
                        <span>
                            Aniara Intelledger · Clarity in every entry.
                        </span>
                        <span>
                            Built for the everyday. Designed for the long term.
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}
