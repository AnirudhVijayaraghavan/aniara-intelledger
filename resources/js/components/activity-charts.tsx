import { Badge } from '@/components/ui/badge';
import { activityChartScale } from '@/lib/activity-chart';
import { formatDecimal } from '@/lib/format-decimal';
import type { MonthlyActivity } from '@/types/financial-activity';

export function MonthlyActivityChart({
    monthly,
    currency,
}: {
    monthly: MonthlyActivity[];
    currency: string;
}) {
    const scale = activityChartScale(
        monthly.flatMap((row) => [
            row.totals.recordedIncome,
            row.totals.recordedExpenses,
        ]),
    );
    if (!monthly.length || !scale) {
        return (
            <p className="text-sm text-muted-foreground">
                The chart is unavailable. Exact recorded values remain in the
                trend table.
            </p>
        );
    }
    const width = Math.max(960, monthly.length * 72 + 48);
    const step = (width - 48) / monthly.length;
    const zeroY = scale.hasNegative ? 128 : 224;
    const extent = scale.hasNegative ? 96 : 192;
    const maximum = scale.maximum || 1;
    return (
        <figure className="grid gap-3">
            <div className="flex flex-wrap gap-4 text-sm">
                <span className="flex items-center gap-2">
                    <span className="h-3 w-3 bg-primary" /> Recorded income
                </span>
                <span className="flex items-center gap-2">
                    <span className="h-3 w-3 bg-accent" /> Recorded expenses
                </span>
                <span className="text-muted-foreground">
                    {currency} only · posting month
                </span>
            </div>
            <div className="overflow-x-auto">
                <svg
                    role="img"
                    aria-label={`Monthly recorded income and expenses in ${currency}; exact amounts in the trend table`}
                    viewBox={`0 0 ${width} 280`}
                    className="block h-72 w-full"
                    style={{ minWidth: width }}
                >
                    <line
                        x1={24}
                        x2={width - 24}
                        y1={zeroY}
                        y2={zeroY}
                        className="stroke-border"
                    />
                    <text
                        x={6}
                        y={zeroY - 6}
                        className="fill-muted-foreground text-[10px]"
                    >
                        0
                    </text>
                    {monthly.map((row, index) => (
                        <g key={row.month}>
                            {[
                                row.totals.recordedIncome,
                                row.totals.recordedExpenses,
                            ].map((amount, series) => {
                                const value = scale.values[index * 2 + series];
                                const height =
                                    (Math.abs(value) / maximum) * extent;
                                return (
                                    <rect
                                        key={series}
                                        x={
                                            24 +
                                            index * step +
                                            step / 2 +
                                            (series === 0 ? -20 : 2)
                                        }
                                        y={value < 0 ? zeroY : zeroY - height}
                                        width={18}
                                        height={height}
                                        className={
                                            series === 0
                                                ? 'fill-primary'
                                                : 'fill-accent'
                                        }
                                    >
                                        <title>
                                            {row.month} ·{' '}
                                            {series === 0
                                                ? 'Recorded income'
                                                : 'Recorded expenses'}
                                            : {formatDecimal(amount, currency)}{' '}
                                            {currency}
                                        </title>
                                    </rect>
                                );
                            })}
                            <text
                                x={24 + index * step + step / 2}
                                y={260}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[11px]"
                            >
                                {row.month}
                            </text>
                        </g>
                    ))}
                </svg>
            </div>
            <figcaption className="text-sm text-muted-foreground">
                Negative bars retain refunds and reversal adjustments. Bar sizes
                are illustrative; labels and tables preserve exact amounts.
            </figcaption>
        </figure>
    );
}

export type ActivityBar = {
    id: string;
    label: string;
    amount: string;
    archived: boolean;
};
export function ActivityBars({
    title,
    rows,
    currency,
    kind,
}: {
    title: string;
    rows: ActivityBar[];
    currency: string;
    kind: 'income' | 'expense';
}) {
    const scale = activityChartScale(rows.map((row) => row.amount));
    const zeroX = scale?.hasNegative ? 120 : 0;
    const extent = scale?.hasNegative ? 112 : 232;
    return (
        <section className="grid content-start gap-4 rounded-md border bg-card p-5">
            <h2 className="font-medium">
                {title} · {currency}
            </h2>
            {!rows.length ? (
                <p className="text-sm text-muted-foreground">
                    No non-zero recorded amounts. Zero net entries remain in the
                    tables.
                </p>
            ) : (
                <ul className="divide-y">
                    {rows.map((row, index) => (
                        <li
                            key={row.id}
                            className="grid gap-2 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2 text-sm">
                                <span className="min-w-0 break-words">
                                    {row.label}{' '}
                                    {row.archived && (
                                        <Badge variant="secondary">
                                            Archived
                                        </Badge>
                                    )}
                                </span>
                                <span className="break-all tabular-nums">
                                    {formatDecimal(row.amount, currency)}{' '}
                                    {currency}
                                </span>
                            </div>
                            {scale && (
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 240 18"
                                    className="h-5 w-full"
                                >
                                    <line
                                        x1={zeroX}
                                        x2={zeroX}
                                        y1={0}
                                        y2={18}
                                        className="stroke-border"
                                    />
                                    <rect
                                        x={
                                            scale.values[index] < 0
                                                ? zeroX -
                                                  (Math.abs(
                                                      scale.values[index],
                                                  ) /
                                                      (scale.maximum || 1)) *
                                                      extent
                                                : zeroX
                                        }
                                        y={3}
                                        width={
                                            (Math.abs(scale.values[index]) /
                                                (scale.maximum || 1)) *
                                            extent
                                        }
                                        height={12}
                                        className={
                                            kind === 'income'
                                                ? 'fill-primary'
                                                : 'fill-accent'
                                        }
                                    />
                                </svg>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {rows.length > 0 && !scale && (
                <p className="text-xs text-muted-foreground">
                    Bar scale unavailable. Exact amounts are shown above.
                </p>
            )}
        </section>
    );
}
