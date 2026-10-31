export function formatUtcTimestamp(value: string): string {
    return value.replace('T', ' ').replace('Z', ' UTC');
}

/** A prior-date rate is flagged for review; this does not change valuation eligibility. */
export function isPriorDate(effectiveAt: string, valuationAt: string): boolean {
    return effectiveAt.slice(0, 10) < valuationAt.slice(0, 10);
}

/** The form explicitly collects UTC wall time, rather than browser-local time. */
export function utcInputToIso(value: string): string {
    if (!value) {
        return '';
    }

    return `${value.length === 16 ? `${value}:00` : value}Z`;
}
