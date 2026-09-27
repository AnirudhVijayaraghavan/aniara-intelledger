import {
    ArrowDownLeft,
    ArrowUpRight,
    ChartNoAxesCombined,
    Wallet,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export default function LedgerPreview({
    compact = false,
}: {
    compact?: boolean;
}) {
    return (
        <section
            aria-label="Illustrative financial overview"
            className={cn(
                'overflow-hidden rounded-md border border-border bg-card text-card-foreground',
                compact && 'ledger-preview-compact shrink-0',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                <span className="flex items-center gap-2 text-sm">
                    <ChartNoAxesCombined className="size-4" /> Financial
                    overview
                </span>
                <span className="rounded-sm bg-secondary px-2 py-1 text-xs text-secondary-foreground">
                    Snapshot · Sample data
                </span>
            </div>
            <div className="p-5 sm:p-7">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Total net worth
                        </p>
                        <p className="mt-2 text-4xl tabular-nums sm:text-5xl">
                            $284,650
                            <span className="text-xl text-muted-foreground">
                                .00
                            </span>
                        </p>
                    </div>
                    <span className="pt-1 text-xs text-muted-foreground">
                        USD
                    </span>
                </div>
                <p className="mt-3 flex items-center gap-1 text-sm">
                    <ArrowUpRight className="size-4" /> +$18,420{' '}
                    <span className="text-muted-foreground">
                        over the last 6 months
                    </span>
                </p>
                <svg
                    viewBox="0 0 600 160"
                    role="img"
                    aria-label="Illustrative net worth trend rising from April to September"
                    className="mt-5 h-36 w-full overflow-visible"
                >
                    {[30, 75, 120].map((y) => (
                        <line
                            key={y}
                            x1="0"
                            x2="600"
                            y1={y}
                            y2={y}
                            className="stroke-border"
                            strokeDasharray="3 5"
                        />
                    ))}
                    <path
                        d="M0 138 L35 133 L70 140 L105 112 L140 117 L175 97 L210 108 L245 75 L280 82 L315 66 L350 76 L385 45 L420 56 L455 31 L490 39 L525 20 L560 25 L600 8 L600 160 L0 160 Z"
                        className="fill-primary/10"
                    />
                    <path
                        d="M0 138 L35 133 L70 140 L105 112 L140 117 L175 97 L210 108 L245 75 L280 82 L315 66 L350 76 L385 45 L420 56 L455 31 L490 39 L525 20 L560 25 L600 8"
                        fill="none"
                        className="stroke-primary"
                        strokeWidth="3"
                        strokeLinejoin="round"
                    />
                    <circle cx="600" cy="8" r="4" className="fill-primary" />
                </svg>
                <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                    <span>APR</span>
                    <span>MAY</span>
                    <span>JUN</span>
                    <span>JUL</span>
                    <span>AUG</span>
                    <span>SEP</span>
                </div>
            </div>
            <dl className="grid grid-cols-3 divide-x divide-border border-t border-border bg-muted/40">
                {[
                    ['Investments', '$196,250'],
                    ['Cash reserves', '$58,400'],
                    ['Other assets', '$30,000'],
                ].map(([label, value]) => (
                    <div key={label} className="px-3 py-4 sm:px-5">
                        <dt className="text-xs text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="mt-1 text-lg tabular-nums sm:text-xl">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            {!compact && (
                <div className="border-t border-border px-5 py-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm">Recent ledger entries</h3>
                        <span className="text-xs text-muted-foreground">
                            Illustrative · USD
                        </span>
                    </div>
                    {[
                        {
                            icon: ArrowDownLeft,
                            label: 'Monthly income',
                            detail: 'Income · 01 Sep',
                            amount: '+$6,800.00',
                        },
                        {
                            icon: Wallet,
                            label: 'Everyday expenses',
                            detail: 'Living · 02 Sep',
                            amount: '−$142.60',
                        },
                    ].map(({ icon: Icon, label, detail, amount }) => (
                        <div
                            key={label}
                            className="flex items-center gap-3 border-t border-border py-3"
                        >
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm">{label}</p>
                                <p className="text-xs text-muted-foreground">
                                    {detail}
                                </p>
                            </div>
                            <span className="text-sm tabular-nums">
                                {amount}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
