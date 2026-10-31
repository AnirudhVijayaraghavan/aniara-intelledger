import { Form } from '@inertiajs/react';
import { Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/teams/reporting-currency';

export default function ReportingCurrencyForm({
    team,
    currencies,
    canUpdate,
}: {
    team: { slug: string; reportingCurrencyCode: string };
    currencies: { code: string; name: string }[];
    canUpdate: boolean;
}) {
    return (
        <section
            className="grid gap-5"
            aria-labelledby="reporting-currency-heading"
        >
            <div id="reporting-currency-heading">
                <Heading
                    variant="small"
                    title="Reporting currency"
                    description="Choose the currency used for the consolidated household valuation. Original account balances keep their currencies."
                />
            </div>
            {canUpdate ? (
                <Form
                    {...update.form(team.slug)}
                    className="grid gap-4"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <div className="grid max-w-sm gap-2">
                                <Label htmlFor="reporting-currency">
                                    Currency
                                </Label>
                                <Select
                                    name="reporting_currency_code"
                                    defaultValue={team.reportingCurrencyCode}
                                    disabled={processing}
                                >
                                    <SelectTrigger
                                        id="reporting-currency"
                                        aria-invalid={
                                            !!errors.reporting_currency_code
                                        }
                                        aria-describedby="reporting-currency-error"
                                    >
                                        <SelectValue />
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
                                    id="reporting-currency-error"
                                    message={errors.reporting_currency_code}
                                />
                            </div>
                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    <Save />
                                    Save reporting currency
                                </Button>
                                {recentlySuccessful && (
                                    <span
                                        role="status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Saved
                                    </span>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            ) : (
                <div className="grid gap-2 text-sm">
                    <p className="font-medium">{team.reportingCurrencyCode}</p>
                    <p className="text-muted-foreground">
                        Only household owners and administrators can change the
                        reporting currency.
                    </p>
                </div>
            )}
        </section>
    );
}
