import { Link, useForm } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { reverse, show } from '@/routes/transactions';
import type { FinancialTeam } from '@/types/finance';
import type { Transaction } from '@/types/transactions';

export default function ReverseTransactionDialog({
    transaction,
    team,
    today,
}: {
    transaction: Transaction;
    team: FinancialTeam;
    today: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        expected_revision: transaction.expected_revision,
        posted_date:
            transaction.posted_date && transaction.posted_date > today
                ? transaction.posted_date
                : today,
        reason: '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(reverse.url([team.slug, transaction.id]), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                <Undo2 /> Reverse Transaction
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!form.processing) {
                        setOpen(value);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reverse posted transaction?</DialogTitle>
                        <DialogDescription>
                            Record an equal and opposite movement in{' '}
                            {transaction.account.name} (
                            {transaction.currency_code}). The original remains
                            in history. This does not move money or change
                            recorded account balances.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="reversal-date">Reversal date</Label>
                            <Input
                                id="reversal-date"
                                type="date"
                                value={form.data.posted_date}
                                min={transaction.posted_date || undefined}
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
                                Used for both the transaction and posting date,
                                on or after the original posting date.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reversal-reason">Reason</Label>
                            <Input
                                id="reversal-reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                maxLength={2000}
                                required
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                        <InputError message={form.errors.expected_revision} />
                        {form.errors.expected_revision && (
                            <Link
                                className="text-sm underline underline-offset-4"
                                href={show([team.slug, transaction.id])}
                                preserveState={false}
                            >
                                Reload the latest transaction before trying
                                again
                            </Link>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Both entries retain the original classification and
                            will become read-only.
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setOpen(false)}
                            >
                                Keep transaction
                            </Button>
                            <Button
                                disabled={
                                    form.processing ||
                                    !form.data.reason.trim() ||
                                    !!form.errors.expected_revision
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
