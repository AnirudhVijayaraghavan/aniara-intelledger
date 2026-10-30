import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftRight, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    formatUtcTimestamp,
    isPriorDate,
    utcInputToIso,
} from '@/lib/financial-dates';
import { formatDecimal } from '@/lib/format-decimal';
import { index, store } from '@/routes/exchange-rates';
import type { ExchangeRateObservation, FinancialTeam } from '@/types/finance';

type Props = {
    team: FinancialTeam;
    currencies: { code: string; name: string }[];
    defaults: {
        base_currency_code: string | null;
        quote_currency_code: string | null;
    };
    canRecord: boolean;
    nowUtc: string;
    rates: {
        data: ExchangeRateObservation[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

export default function ExchangeRates({
    team,
    currencies,
    defaults,
    canRecord,
    nowUtc,
    rates,
}: Props) {
    const form = useForm({
        base_currency_code: defaults.base_currency_code ?? '',
        quote_currency_code: defaults.quote_currency_code ?? '',
        rate: '',
        effective_at: nowUtc.slice(0, 19),
    });
    const { data, setData, errors, processing } = form;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((input) => ({
            ...input,
            effective_at: utcInputToIso(input.effective_at),
        }));
        form.submit(store(team.slug), {
            preserveScroll: true,
            onSuccess: () => form.reset('rate'),
        });
    };

    return (
        <>
            <Head title="FX rates" />
            <div className="flex min-w-0 flex-col gap-6 p-4 md:p-6">
                <header className="grid gap-2">
                    <h1 className="text-xl font-semibold">FX rates</h1>
                    <p className="text-sm text-muted-foreground">
                        Historical exchange rates for {team.name}. Manual
                        observations are used only when effective at or before
                        the valuation cutoff.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Rates are not fetched or refreshed automatically.
                        Prior-date rates may be stale and should be reviewed.
                    </p>
                </header>
                {canRecord ? (
                    <section
                        aria-labelledby="record-fx"
                        className="grid max-w-4xl gap-5 rounded-md border bg-card p-5"
                    >
                        <div className="grid gap-2">
                            <h2 id="record-fx" className="font-medium">
                                Record a manual rate
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Convention: 1 BASE = rate QUOTE. Corrections
                                append a new observation and preserve the
                                earlier record.
                            </p>
                        </div>
                        <form
                            onSubmit={submit}
                            className="grid gap-5 sm:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="fx-base">Base currency</Label>
                                <Select
                                    value={data.base_currency_code}
                                    disabled={processing}
                                    onValueChange={(value) =>
                                        setData((current) => ({
                                            ...current,
                                            base_currency_code: value,
                                            quote_currency_code:
                                                current.quote_currency_code ===
                                                value
                                                    ? (currencies.find(
                                                          (currency) =>
                                                              currency.code !==
                                                              value,
                                                      )?.code ?? '')
                                                    : current.quote_currency_code,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="fx-base"
                                        aria-invalid={
                                            !!errors.base_currency_code
                                        }
                                        aria-describedby="fx-base-error"
                                    >
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencies.map((currency) => (
                                            <SelectItem
                                                key={currency.code}
                                                value={currency.code}
                                            >
                                                {currency.code} ·{' '}
                                                {currency.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="fx-base-error"
                                    message={errors.base_currency_code}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fx-quote">Quote currency</Label>
                                <Select
                                    value={data.quote_currency_code}
                                    disabled={processing}
                                    onValueChange={(value) =>
                                        setData('quote_currency_code', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="fx-quote"
                                        aria-invalid={
                                            !!errors.quote_currency_code
                                        }
                                        aria-describedby="fx-quote-error"
                                    >
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencies
                                            .filter(
                                                (currency) =>
                                                    currency.code !==
                                                    data.base_currency_code,
                                            )
                                            .map((currency) => (
                                                <SelectItem
                                                    key={currency.code}
                                                    value={currency.code}
                                                >
                                                    {currency.code} ·{' '}
                                                    {currency.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="fx-quote-error"
                                    message={errors.quote_currency_code}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fx-rate">
                                    Rate · {data.quote_currency_code || 'QUOTE'}{' '}
                                    per 1 {data.base_currency_code || 'BASE'}
                                </Label>
                                <Input
                                    id="fx-rate"
                                    type="text"
                                    inputMode="decimal"
                                    maxLength={80}
                                    value={data.rate}
                                    onChange={(event) =>
                                        setData('rate', event.target.value)
                                    }
                                    required
                                    aria-invalid={!!errors.rate}
                                    aria-describedby="fx-rate-error fx-rate-hint"
                                />
                                <p
                                    id="fx-rate-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Positive decimal, up to 18 decimal places.
                                    Use a decimal point without commas.
                                </p>
                                <InputError
                                    id="fx-rate-error"
                                    message={errors.rate}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fx-effective">
                                    Effective time (UTC)
                                </Label>
                                <Input
                                    id="fx-effective"
                                    type="datetime-local"
                                    step="1"
                                    value={data.effective_at}
                                    onChange={(event) =>
                                        setData(
                                            'effective_at',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    aria-invalid={!!errors.effective_at}
                                    aria-describedby="fx-effective-error fx-effective-hint"
                                />
                                <p
                                    id="fx-effective-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Entered as UTC. Recording time is assigned
                                    automatically.
                                </p>
                                <InputError
                                    id="fx-effective-error"
                                    message={errors.effective_at}
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="justify-self-start sm:col-span-2"
                            >
                                <Plus />
                                {processing ? 'Recording…' : 'Record rate'}
                            </Button>
                        </form>
                    </section>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        You can view household FX history. Only owners and
                        administrators can record manual rates.
                    </p>
                )}
                <section className="grid gap-4" aria-labelledby="fx-history">
                    <h2 id="fx-history" className="font-medium">
                        Rate history
                    </h2>
                    {rates.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-md border border-dashed bg-card p-10 text-center">
                            <ArrowLeftRight className="size-7 text-muted-foreground" />
                            <h3 className="font-medium">
                                No exchange rates recorded
                            </h3>
                            <p className="max-w-lg text-sm text-muted-foreground">
                                Original currency balances remain available.
                                Cross-currency consolidated totals need an
                                eligible manual rate.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Household exchange rate observations, newest
                                    effective time first
                                </caption>
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        {[
                                            'Base',
                                            'Quote',
                                            'Rate (QUOTE per BASE)',
                                            'Effective at (UTC)',
                                            'Recorded at (UTC)',
                                            'Source / review',
                                        ].map((label) => (
                                            <th
                                                scope="col"
                                                key={label}
                                                className="px-4 py-3 font-medium whitespace-nowrap"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rates.data.map((rate) => (
                                        <tr key={rate.id}>
                                            <td className="px-4 py-4 font-medium">
                                                {rate.baseCurrencyCode}
                                            </td>
                                            <td className="px-4 py-4">
                                                {rate.quoteCurrencyCode}
                                            </td>
                                            <td className="px-4 py-4 text-right whitespace-nowrap tabular-nums">
                                                {formatDecimal(
                                                    rate.rate,
                                                    rate.quoteCurrencyCode,
                                                )}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <time
                                                    dateTime={rate.effectiveAt}
                                                >
                                                    {formatUtcTimestamp(
                                                        rate.effectiveAt,
                                                    )}
                                                </time>
                                                <span className="block text-xs text-muted-foreground">
                                                    Observation #{rate.id}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <time
                                                    dateTime={rate.recordedAt}
                                                >
                                                    {formatUtcTimestamp(
                                                        rate.recordedAt,
                                                    )}
                                                </time>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex min-w-40 flex-wrap gap-2">
                                                    <Badge variant="secondary">
                                                        {rate.source ===
                                                        'manual'
                                                            ? 'Manual'
                                                            : rate.source}
                                                    </Badge>
                                                    {rate.effectiveAt >
                                                    nowUtc ? (
                                                        <Badge variant="outline">
                                                            Not yet effective
                                                        </Badge>
                                                    ) : (
                                                        isPriorDate(
                                                            rate.effectiveAt,
                                                            nowUtc,
                                                        ) && (
                                                            <Badge variant="outline">
                                                                Prior-date · May
                                                                be stale
                                                            </Badge>
                                                        )
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {rates.last_page > 1 && (
                        <nav
                            aria-label="FX history pages"
                            className="flex items-center justify-between gap-4"
                        >
                            <Button asChild variant="outline">
                                <Link
                                    href={index(team.slug, {
                                        query: {
                                            page: Math.max(
                                                1,
                                                rates.current_page - 1,
                                            ),
                                        },
                                    })}
                                    aria-disabled={rates.current_page === 1}
                                    className={
                                        rates.current_page === 1
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                >
                                    Previous
                                </Link>
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                Page {rates.current_page} of {rates.last_page}
                            </span>
                            <Button asChild variant="outline">
                                <Link
                                    href={index(team.slug, {
                                        query: {
                                            page: Math.min(
                                                rates.last_page,
                                                rates.current_page + 1,
                                            ),
                                        },
                                    })}
                                    aria-disabled={
                                        rates.current_page === rates.last_page
                                    }
                                    className={
                                        rates.current_page === rates.last_page
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                >
                                    Next
                                </Link>
                            </Button>
                        </nav>
                    )}
                </section>
            </div>
        </>
    );
}

ExchangeRates.layout = (props: Props) => ({
    breadcrumbs: [{ title: 'FX rates', href: index(props.team.slug) }],
});
