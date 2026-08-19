import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Landmark,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="A clearer financial ledger" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-7xl items-center justify-between border-b border-border px-6 py-5 lg:px-8">
                    <Link
                        href={dashboard()}
                        className="flex items-center gap-3"
                    >
                        <span className="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <Landmark className="size-5" />
                        </span>
                        <span className="text-lg font-semibold">
                            Aniara Intelledger
                        </span>
                    </Link>

                    <nav className="flex items-center gap-3 text-sm">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                Open ledger <ArrowRight className="size-4" />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="hidden rounded-md px-3 py-2 font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-secondary-foreground sm:inline-flex"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Create account{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto grid w-full max-w-7xl gap-12 px-6 py-12 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:px-8 lg:py-20">
                    <section className="flex max-w-xl flex-col justify-center">
                        <p className="mb-5 flex items-center gap-2 text-sm font-medium text-primary">
                            <ShieldCheck className="size-4" />
                            Your financial record, in one place
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold text-balance sm:text-5xl">
                            One ledger for your wealth across the U.S. and
                            India.
                        </h1>
                        <p className="mt-6 max-w-lg text-lg leading-8 text-muted-foreground">
                            Consolidate accounts, understand spending, follow
                            markets, and make better-informed financial
                            decisions from one calm operating surface.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={auth.user ? dashboard() : register()}
                                className="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                {auth.user
                                    ? 'Open your ledger'
                                    : 'Start your ledger'}
                                <ArrowRight className="size-4" />
                            </Link>
                            {!auth.user && (
                                <Link
                                    href={login()}
                                    className="inline-flex h-10 items-center rounded-md border border-border px-5 text-sm font-medium transition-colors hover:bg-secondary"
                                >
                                    Log in
                                </Link>
                            )}
                        </div>
                        <div className="mt-10 grid gap-4 border-t border-border pt-6 sm:grid-cols-3">
                            <Feature
                                icon={Landmark}
                                label="Wealth"
                                detail="USD & INR"
                            />
                            <Feature
                                icon={WalletCards}
                                label="Spending"
                                detail="Clear categories"
                            />
                            <Feature
                                icon={BarChart3}
                                label="Markets"
                                detail="Watch with context"
                            />
                        </div>
                    </section>

                    <LedgerSurface />
                </main>
            </div>
        </>
    );
}

function Feature({
    icon: Icon,
    label,
    detail,
}: {
    icon: typeof Landmark;
    label: string;
    detail: string;
}) {
    return (
        <div className="flex items-center gap-3">
            <span className="flex size-8 items-center justify-center rounded-md bg-secondary text-primary">
                <Icon className="size-4" />
            </span>
            <span className="text-sm leading-5">
                <span className="block font-medium">{label}</span>
                <span className="block text-muted-foreground">{detail}</span>
            </span>
        </div>
    );
}

function LedgerSurface() {
    return (
        <section className="overflow-hidden rounded-md border border-border bg-card shadow-sm motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2">
            <div className="flex items-center justify-between border-b border-border bg-secondary/60 px-5 py-4">
                <div>
                    <p className="text-xs font-medium text-muted-foreground">
                        CONSOLIDATED POSITION
                    </p>
                    <p className="mt-1 text-xl font-semibold">
                        Household ledger
                    </p>
                </div>
                <span className="rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground">
                    Snapshot
                </span>
            </div>

            <div className="grid gap-px bg-border sm:grid-cols-3">
                <Metric
                    label="Net worth"
                    value="$248,420"
                    change="+4.8% this year"
                />
                <Metric label="India holdings" value="₹18.6L" change="Synced" />
                <Metric label="Cash runway" value="7.4 mo" change="On track" />
            </div>

            <div className="grid gap-6 p-5 sm:grid-cols-[1.1fr_0.9fr]">
                <div>
                    <div className="mb-4 flex items-center justify-between">
                        <p className="text-sm font-medium">
                            Allocation overview
                        </p>
                        <span className="text-xs text-muted-foreground">
                            USD equivalent
                        </span>
                    </div>
                    <div className="flex h-28 items-end gap-2 border-b border-border pb-1">
                        {[48, 68, 54, 82, 62, 92, 76, 100].map(
                            (height, index) => (
                                <span
                                    key={height}
                                    className={
                                        index === 7
                                            ? 'flex-1 rounded-t-sm bg-primary'
                                            : 'flex-1 rounded-t-sm bg-secondary'
                                    }
                                    style={{ height: `${height}%` }}
                                />
                            ),
                        )}
                    </div>
                    <div className="mt-3 flex justify-between text-xs text-muted-foreground">
                        <span>Jan</span>
                        <span>Apr</span>
                        <span>Jul</span>
                        <span>Today</span>
                    </div>
                </div>
                <div className="border-t border-border pt-5 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-6">
                    <p className="mb-3 text-sm font-medium">Watchlist</p>
                    <div className="space-y-3">
                        <WatchRow symbol="VTI" value="$286.14" change="+0.8%" />
                        <WatchRow
                            symbol="NIFTY 50"
                            value="24,771"
                            change="+0.4%"
                        />
                        <WatchRow
                            symbol="USD / INR"
                            value="₹83.42"
                            change="Market"
                            muted
                        />
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-2 border-t border-border bg-muted/50 px-5 py-3 text-xs text-muted-foreground">
                <ShieldCheck className="size-4 text-primary" />
                Designed for financial clarity, not automated trading.
            </div>
        </section>
    );
}

function Metric({
    label,
    value,
    change,
}: {
    label: string;
    value: string;
    change: string;
}) {
    return (
        <div className="bg-card p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
            <p className="mt-1 text-xs text-primary">{change}</p>
        </div>
    );
}

function WatchRow({
    symbol,
    value,
    change,
    muted = false,
}: {
    symbol: string;
    value: string;
    change: string;
    muted?: boolean;
}) {
    return (
        <div className="flex items-center justify-between border-b border-border pb-2 last:border-0 last:pb-0">
            <span className="text-sm font-medium">{symbol}</span>
            <span className="text-right text-sm tabular-nums">
                <span className="block font-medium">{value}</span>
                <span
                    className={
                        muted
                            ? 'text-xs text-muted-foreground'
                            : 'text-xs text-primary'
                    }
                >
                    {change}
                </span>
            </span>
        </div>
    );
}
