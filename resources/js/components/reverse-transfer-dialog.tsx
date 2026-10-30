import { Link, useForm } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { accountEffect } from '@/components/transfer-leg';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDecimal } from '@/lib/format-decimal';
import { reverse, show } from '@/routes/transfers';
import type { FinancialTeam } from '@/types/finance';
import type { Transfer, TransferReversalPreview } from '@/types/transfers';

export default function ReverseTransferDialog({
    team,
    transfer,
    preview,
    today,
}: {
    team: FinancialTeam;
    transfer: Transfer;
    preview: TransferReversalPreview;
    today: string;
}) {
    const [open, setOpen] = useState(false);
    const [requestError, setRequestError] = useState('');
    const form = useForm({
        expected_source_revision: transfer.source.expected_revision,
        expected_destination_revision: transfer.destination.expected_revision,
        posted_date:
            today < preview.minimum_posted_date
                ? preview.minimum_posted_date
                : today,
        reason: '',
    });
    const stale =
        form.errors.expected_source_revision ||
        form.errors.expected_destination_revision;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(reverse.url([team.slug, transfer.id]), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onHttpException: (response) => {
                if ([401, 403, 404, 409, 419].includes(response.status)) {
                    setRequestError(
                        response.status === 403
                            ? 'You are no longer authorized to reverse this transfer. Reload to check your access.'
                            : 'This transfer or your session is no longer current. Reload before continuing.',
                    );
                    return false;
                }
            },
            onNetworkError: () => {
                setRequestError(
                    'The result could not be confirmed. Reload this transfer before trying again.',
                );
                return false;
            },
        });
    };

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                <Undo2 /> Reverse Transfer
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!form.processing) {
                        setOpen(value);
                    }
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Reverse this completed transfer?
                        </DialogTitle>
                        <DialogDescription>
                            Two new posted transactions will exactly offset
                            these original movements. Existing transactions and
                            audit history remain. This records a correction; it
                            does not move money or change recorded balances.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="divide-y border-y">
                        {[
                            {
                                leg: transfer.source,
                                amount: preview.source_amount,
                                originalSource: true,
                            },
                            {
                                leg: transfer.destination,
                                amount: preview.destination_amount,
                                originalSource: false,
                            },
                        ].map(({ leg, amount, originalSource }) => (
                            <div key={leg.id} className="grid gap-2 py-4">
                                <p className="font-medium">
                                    {leg.account.name} · {leg.account.typeLabel}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {leg.account.institution?.name ||
                                        'Institution not recorded'}
                                </p>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Original account movement
                                        </dt>
                                        <dd className="break-all tabular-nums">
                                            {formatDecimal(
                                                leg.amount,
                                                leg.currency_code,
                                            )}{' '}
                                            {leg.currency_code}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            New reversal movement
                                        </dt>
                                        <dd className="break-all tabular-nums">
                                            {amount.startsWith('-') ? '' : '+'}
                                            {formatDecimal(
                                                amount,
                                                leg.currency_code,
                                            )}{' '}
                                            {leg.currency_code}
                                        </dd>
                                    </div>
                                </dl>
                                <p className="text-sm text-muted-foreground">
                                    {accountEffect(
                                        leg.account,
                                        !originalSource,
                                    )}
                                </p>
                            </div>
                        ))}
                    </div>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="transfer-reversal-date">
                                Reversal posting date
                            </Label>
                            <Input
                                id="transfer-reversal-date"
                                type="date"
                                value={form.data.posted_date}
                                min={preview.minimum_posted_date}
                                onChange={(event) =>
                                    form.setData(
                                        'posted_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.posted_date} />
                            <p className="text-sm text-muted-foreground">
                                Used for both new transaction dates and posting
                                dates, on or after both original posting dates.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="transfer-reversal-reason">
                                Reason
                            </Label>
                            <Input
                                id="transfer-reversal-reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                maxLength={2000}
                                required
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                        <InputError message={stale} />
                        <InputError message={requestError} />
                        {(stale || requestError) && (
                            <Link
                                className="text-sm underline underline-offset-4"
                                href={show([team.slug, transfer.id])}
                                preserveState={false}
                            >
                                Reload latest transfer
                            </Link>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Both booked currencies are preserved. Historical FX
                            remains attached to the original transfer; no
                            current exchange rate is applied.
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setOpen(false)}
                            >
                                Keep transfer
                            </Button>
                            <Button
                                disabled={
                                    form.processing ||
                                    !form.data.reason.trim() ||
                                    !!stale ||
                                    !!requestError
                                }
                            >
                                <Undo2 /> Confirm reversal
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
