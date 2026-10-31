/** Approximate display coordinates only. Never use these numbers for financial totals or labels. */
export function activityChartScale(amounts: readonly string[]): {
    values: number[];
    maximum: number;
    hasNegative: boolean;
} | null {
    const values: number[] = [];
    let maximum = 0;
    let hasNegative = false;
    for (const amount of amounts) {
        if (!/^-?[0-9]+(?:\.[0-9]+)?$/.test(amount)) {
            return null;
        }
        const coordinate = Number(amount);
        if (!Number.isFinite(coordinate)) {
            return null;
        }
        values.push(coordinate);
        maximum = Math.max(maximum, Math.abs(coordinate));
        hasNegative ||= coordinate < 0;
    }
    return { values, maximum, hasNegative };
}

/** Presence check for presentation; zero-valued buckets remain available in the tables. */
export function hasRecordedAmount(amount: string): boolean {
    return !/^-?0+(?:\.0+)?$/.test(amount);
}
