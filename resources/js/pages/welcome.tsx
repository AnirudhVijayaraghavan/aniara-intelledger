import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Landmark,
    LineChart,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import { dashboard, home, login, register } from '@/routes';

const pillars = [
    {
        icon: WalletCards,
        label: 'Accounts',
        value: 'US + India',
    },
    {
        icon: BarChart3,
        label: 'Budgeting',
        value: 'Monthly control',
    },
    {
        icon: LineChart,
        label: 'Markets',
        value: 'Watchlists',
    },
];

const ledgerRows = [
    ['Chase operating', '+$4,280.00', 'Cleared'],
    ['Robinhood holdings', '+2.7%', 'Market'],
    ['ICICI savings', 'INR 8.4L', 'Manual'],
    ['Family net worth', '$142,880', 'Snapshot'],
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Aniara Intelledger">
                <meta
                    head-key="description"
                    name="description"
                    content="A private financial ledger for accounts, investments, budgets, and household net worth across the USA and India."
                />
            </Head>

            <main className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <Link href={home()} className="flex items-center gap-3">
                        <span className="flex size-9 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                            AI
                        </span>
                        <span className="text-base font-semibold">
                            Aniara Intelledger
                        </span>
                    </Link>

                    <nav className="flex items-center gap-2 text-sm">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                            >
                                Dashboard <ArrowRight className="size-4" />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-flex h-9 items-center rounded-md px-4 font-medium text-foreground transition hover:bg-secondary"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                >
                                    Register <ArrowRight className="size-4" />
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <section className="mx-auto grid w-full max-w-6xl items-center gap-10 px-6 pt-10 pb-16 lg:grid-cols-[1fr_0.92fr] lg:pt-20">
                    <div className="animate-in duration-700 fade-in slide-in-from-bottom-3">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-md border border-border bg-secondary px-3 py-1 text-sm font-medium text-secondary-foreground">
                            <ShieldCheck className="size-4 text-primary" />
                            Private ledger for household wealth
                        </div>
                        <h1 className="max-w-3xl text-4xl leading-tight font-semibold text-balance md:text-6xl">
                            One financial command desk for accounts,
                            investments, budgets, and net worth.
                        </h1>
                        <p className="mt-5 max-w-2xl text-lg leading-8 text-muted-foreground">
                            Built for USA and India finances with a careful path
                            from manual tracking to trusted integrations, market
                            data, and auditable AI insight.
                        </p>

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                href={auth.user ? dashboard() : register()}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                            >
                                Open ledger <ArrowRight className="size-4" />
                            </Link>
                            <Link
                                href={auth.user ? dashboard() : login()}
                                className="inline-flex h-11 items-center justify-center rounded-md border border-border bg-background px-6 text-sm font-semibold transition hover:bg-secondary"
                            >
                                Review access
                            </Link>
                        </div>
                    </div>

                    <div className="animate-in duration-1000 fade-in slide-in-from-bottom-4">
                        <div className="overflow-hidden rounded-md border border-border bg-card shadow-xl shadow-[#2ec4b6]/10">
                            <div className="flex items-center justify-between border-b border-border bg-secondary px-5 py-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Household net worth
                                    </p>
                                    <p className="text-3xl font-semibold">
                                        $142,880
                                    </p>
                                </div>
                                <div className="rounded-md bg-accent px-3 py-1 text-sm font-semibold text-accent-foreground">
                                    +4.8%
                                </div>
                            </div>

                            <div className="grid gap-4 p-5">
                                <div className="grid grid-cols-12 items-end gap-2 rounded-md border border-border bg-background p-4">
                                    {[
                                        36, 44, 41, 52, 58, 64, 61, 70, 76, 81,
                                    ].map((height, index) => (
                                        <div
                                            key={index}
                                            className="rounded-sm bg-primary/80"
                                            style={{
                                                height: `${height}px`,
                                            }}
                                        />
                                    ))}
                                    <div className="col-span-2 flex h-24 items-end rounded-sm bg-accent" />
                                </div>

                                <div className="divide-y divide-border rounded-md border border-border">
                                    {ledgerRows.map((row) => (
                                        <div
                                            key={row[0]}
                                            className="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-4 py-3 text-sm"
                                        >
                                            <span className="font-medium">
                                                {row[0]}
                                            </span>
                                            <span className="font-semibold">
                                                {row[1]}
                                            </span>
                                            <span className="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                                                {row[2]}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="border-y border-border bg-muted">
                    <div className="mx-auto grid max-w-6xl gap-4 px-6 py-8 md:grid-cols-3">
                        {pillars.map(({ icon: Icon, label, value }) => (
                            <div
                                key={label}
                                className="flex items-center justify-between rounded-md border border-border bg-card p-5"
                            >
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-xl font-semibold">
                                        {value}
                                    </p>
                                </div>
                                <Icon className="size-6 text-primary" />
                            </div>
                        ))}
                    </div>
                </section>

                <section className="mx-auto grid max-w-6xl gap-6 px-6 py-12 md:grid-cols-3">
                    <div className="rounded-md border border-border bg-card p-6">
                        <Landmark className="mb-4 size-6 text-primary" />
                        <h2 className="text-lg font-semibold">
                            Connected when possible
                        </h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            Provider integrations can sync accounts and holdings
                            while manual ledgers keep unsupported banks usable.
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-card p-6">
                        <BarChart3 className="mb-4 size-6 text-primary" />
                        <h2 className="text-lg font-semibold">
                            Built for decisions
                        </h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            Spending, budgets, markets, and net worth stay in
                            one place so the household view is always clear.
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-card p-6">
                        <ShieldCheck className="mb-4 size-6 text-primary" />
                        <h2 className="text-lg font-semibold">Audit first</h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            Sensitive actions, AI outputs, and provider syncs
                            are designed to be traceable from the start.
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}
