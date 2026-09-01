import { Head } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Landmark,
    WalletCards,
} from 'lucide-react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-5 overflow-x-auto p-4 md:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Tuesday, August 25
                        </p>
                        <h1 className="font-serif text-2xl font-semibold text-foreground">
                            Financial overview
                        </h1>
                    </div>
                    <span className="inline-flex w-fit items-center rounded-sm bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground">
                        Snapshot · Sample data
                    </span>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        label="Net worth"
                        value="$248,340.00"
                        change="+$4,281.24"
                        positive
                    />
                    <Metric
                        label="Investments"
                        value="$176,920.00"
                        change="+2.48%"
                        positive
                    />
                    <Metric
                        label="Available cash"
                        value="$42,880.00"
                        change="3 accounts"
                    />
                    <Metric
                        label="Monthly spending"
                        value="$5,420.00"
                        change="-8.12%"
                        positive
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-5">
                    <section className="rounded-md border border-border bg-card p-5 xl:col-span-3">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-serif text-lg font-semibold">
                                    Portfolio balance
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Last 30 days · Sample performance
                                </p>
                            </div>
                            <span className="inline-flex items-center gap-1 text-sm font-medium text-primary">
                                <ArrowUpRight className="size-4" /> 4.82%
                            </span>
                        </div>
                        <div
                            className="mt-8 flex h-40 items-end gap-2"
                            aria-label="Sample portfolio balance chart"
                        >
                            {[
                                34, 46, 42, 58, 51, 68, 64, 78, 72, 86, 82, 96,
                            ].map((height, index) => (
                                <div
                                    key={index}
                                    className="flex-1 rounded-sm bg-primary/20 last:bg-primary"
                                    style={{ height: `${height}%` }}
                                />
                            ))}
                        </div>
                        <div className="mt-3 flex justify-between text-xs text-muted-foreground">
                            <span>Jul 27</span>
                            <span>Aug 25</span>
                        </div>
                    </section>

                    <section className="rounded-md border border-border bg-card xl:col-span-2">
                        <div className="flex items-center justify-between border-b border-border px-5 py-4">
                            <div>
                                <h2 className="font-serif text-lg font-semibold">
                                    Accounts
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    All accounts synced
                                </p>
                            </div>
                            <Landmark className="size-5 text-primary" />
                        </div>
                        <div className="divide-y divide-border">
                            <Account
                                name="Everyday checking"
                                type="Cash · Synced"
                                value="$18,240.00"
                            />
                            <Account
                                name="Brokerage portfolio"
                                type="Investments · Market"
                                value="$176,920.00"
                            />
                            <Account
                                name="High-yield savings"
                                type="Cash · Synced"
                                value="$24,640.00"
                            />
                        </div>
                    </section>
                </div>

                <section className="rounded-md border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-5 py-4">
                        <div>
                            <h2 className="font-serif text-lg font-semibold">
                                Recent activity
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                A few example ledger entries
                            </p>
                        </div>
                        <WalletCards className="size-5 text-primary" />
                    </div>
                    <div className="divide-y divide-border">
                        <Activity
                            name="Northwind Market"
                            category="Groceries · Everyday checking"
                            amount="-$86.42"
                        />
                        <Activity
                            name="Monthly transfer"
                            category="Savings · High-yield savings"
                            amount="+$750.00"
                            income
                        />
                        <Activity
                            name="Sample dividend"
                            category="Investments · Brokerage portfolio"
                            amount="+$124.16"
                            income
                        />
                    </div>
                </section>
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    change,
    positive = false,
}: {
    label: string;
    value: string;
    change: string;
    positive?: boolean;
}) {
    return (
        <section className="rounded-md border border-border bg-card p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 font-serif text-xl font-semibold tabular-nums">
                {value}
            </p>
            <p
                className={`mt-2 flex items-center gap-1 text-xs font-medium ${positive ? 'text-primary' : 'text-muted-foreground'}`}
            >
                {positive && <ArrowUpRight className="size-3.5" />}
                {change}
            </p>
        </section>
    );
}

function Account({
    name,
    type,
    value,
}: {
    name: string;
    type: string;
    value: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-5 py-3.5">
            <div>
                <p className="text-sm font-medium">{name}</p>
                <p className="text-xs text-muted-foreground">{type}</p>
            </div>
            <p className="font-serif text-sm font-semibold tabular-nums">
                {value}
            </p>
        </div>
    );
}

function Activity({
    name,
    category,
    amount,
    income = false,
}: {
    name: string;
    category: string;
    amount: string;
    income?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-5 py-3.5">
            <div>
                <p className="text-sm font-medium">{name}</p>
                <p className="text-xs text-muted-foreground">{category}</p>
            </div>
            <p
                className={`flex items-center gap-1 font-serif text-sm font-semibold tabular-nums ${income ? 'text-primary' : ''}`}
            >
                {!income && (
                    <ArrowDownRight className="size-3.5 text-muted-foreground" />
                )}
                {amount}
            </p>
        </div>
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
