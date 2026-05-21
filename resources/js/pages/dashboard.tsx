import { Head } from '@inertiajs/react';
import {
    ArrowUpRight,
    Banknote,
    BarChart3,
    Landmark,
    LineChart,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import { dashboard } from '@/routes';

const summaryCards = [
    {
        label: 'Net worth',
        value: '$142,880',
        change: '+4.8% this month',
        icon: Landmark,
    },
    {
        label: 'Investments',
        value: '$86,420',
        change: '+2.7% market move',
        icon: LineChart,
    },
    {
        label: 'Cash runway',
        value: '8.6 months',
        change: '$12,400 available',
        icon: WalletCards,
    },
];

const accounts = [
    ['Chase checking', '$8,940.22', 'Synced'],
    ['Robinhood taxable', '$42,110.80', 'Market'],
    ['ICICI savings', 'INR 8,42,000', 'Manual'],
    ['Family reserve', '$18,500.00', 'Protected'],
];

const watchlist = [
    ['AAPL', '$196.44', '+1.2%'],
    ['VOO', '$512.80', '+0.4%'],
    ['RELIANCE', 'INR 2,914', '-0.3%'],
    ['HDFCBANK', 'INR 1,674', '+0.8%'],
];

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-5 overflow-x-auto p-4 md:p-6">
                <section className="rounded-md border border-border bg-card p-5">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <div className="mb-3 inline-flex items-center gap-2 rounded-md bg-secondary px-3 py-1 text-sm font-medium text-secondary-foreground">
                                <ShieldCheck className="size-4 text-primary" />
                                Household ledger
                            </div>
                            <h1 className="text-3xl font-semibold md:text-4xl">
                                Financial command desk
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                Track cash, portfolios, budgets, and family net
                                worth across USA and India accounts from one
                                controlled workspace.
                            </p>
                        </div>

                        <div className="grid min-w-full grid-cols-2 gap-3 text-sm sm:min-w-80">
                            <div className="rounded-md border border-border bg-muted p-4">
                                <p className="text-muted-foreground">
                                    Monthly spend
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    $4,210
                                </p>
                            </div>
                            <div className="rounded-md border border-border bg-muted p-4">
                                <p className="text-muted-foreground">
                                    Budget left
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    $1,790
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    {summaryCards.map(
                        ({ label, value, change, icon: Icon }) => (
                            <div
                                key={label}
                                className="rounded-md border border-border bg-card p-5"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {label}
                                        </p>
                                        <p className="mt-2 text-3xl font-semibold">
                                            {value}
                                        </p>
                                    </div>
                                    <span className="flex size-10 items-center justify-center rounded-md bg-secondary text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                </div>
                                <p className="mt-5 inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-sm text-muted-foreground">
                                    <ArrowUpRight className="size-4 text-primary" />
                                    {change}
                                </p>
                            </div>
                        ),
                    )}
                </section>

                <section className="grid flex-1 gap-4 xl:grid-cols-[1.25fr_0.75fr]">
                    <div className="rounded-md border border-border bg-card">
                        <div className="flex items-center justify-between border-b border-border px-5 py-4">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Net worth trend
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Snapshot preview
                                </p>
                            </div>
                            <BarChart3 className="size-5 text-primary" />
                        </div>
                        <div className="grid grid-cols-12 items-end gap-2 p-5">
                            {[
                                38, 42, 49, 45, 56, 61, 66, 71, 68, 78, 84, 92,
                            ].map((height, index) => (
                                <div
                                    key={index}
                                    className="rounded-sm bg-primary/80"
                                    style={{ height }}
                                />
                            ))}
                        </div>
                        <div className="grid gap-3 border-t border-border p-5 md:grid-cols-2">
                            <div className="rounded-md bg-muted p-4">
                                <p className="text-sm text-muted-foreground">
                                    Assets
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    $168,420
                                </p>
                            </div>
                            <div className="rounded-md bg-muted p-4">
                                <p className="text-sm text-muted-foreground">
                                    Liabilities
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    $25,540
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4">
                        <div className="rounded-md border border-border bg-card">
                            <div className="flex items-center justify-between border-b border-border px-5 py-4">
                                <h2 className="text-lg font-semibold">
                                    Accounts
                                </h2>
                                <Banknote className="size-5 text-primary" />
                            </div>
                            <div className="divide-y divide-border">
                                {accounts.map(([name, value, status]) => (
                                    <div
                                        key={name}
                                        className="grid grid-cols-[1fr_auto] gap-3 px-5 py-4 text-sm"
                                    >
                                        <div>
                                            <p className="font-semibold">
                                                {name}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {status}
                                            </p>
                                        </div>
                                        <p className="font-semibold">{value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-md border border-border bg-card">
                            <div className="flex items-center justify-between border-b border-border px-5 py-4">
                                <h2 className="text-lg font-semibold">
                                    Watchlist
                                </h2>
                                <LineChart className="size-5 text-primary" />
                            </div>
                            <div className="divide-y divide-border">
                                {watchlist.map(([symbol, price, change]) => (
                                    <div
                                        key={symbol}
                                        className="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-5 py-3 text-sm"
                                    >
                                        <p className="font-semibold">
                                            {symbol}
                                        </p>
                                        <p>{price}</p>
                                        <p className="rounded-md bg-secondary px-2 py-1 text-secondary-foreground">
                                            {change}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
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
            href: dashboard(),
        },
    ],
};
