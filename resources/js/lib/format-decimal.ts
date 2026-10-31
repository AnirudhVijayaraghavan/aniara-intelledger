/** Group decimal digits without rounding or converting monetary values to Number. */
export function formatDecimal(amount: string, currencyCode: string): string {
    if (!/^-?[0-9]+(?:\.[0-9]+)?$/.test(amount)) {
        throw new TypeError('Expected a plain decimal string.');
    }

    const negative = amount.startsWith('-');
    const [integer, fraction = ''] = (
        negative ? amount.slice(1) : amount
    ).split('.');
    const grouped =
        currencyCode === 'INR' && integer.length > 3
            ? `${integer.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',')},${integer.slice(-3)}`
            : integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimals = fraction.replace(/0+$/, '').padEnd(2, '0');

    return `${negative ? '-' : ''}${grouped}.${decimals}`;
}
