import { Head, Link, usePage } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ReverseTransferDialog from '@/components/reverse-transfer-dialog';
import TransferLegPanel from '@/components/transfer-leg';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatUtcTimestamp } from '@/lib/financial-dates';
import { formatDecimal } from '@/lib/format-decimal';
import { index, show } from '@/routes/transfers';
import type { FinancialTeam } from '@/types/finance';
import type { Transfer, TransferReversalPreview } from '@/types/transfers';

export default function TransferDetails({
    team,
    transfer,
    canReverse,
    reversalPreview,
    reversalReason,
    today,
}: {
    team: FinancialTeam;
    transfer: Transfer;
    canReverse: boolean;
    reversalPreview: TransferReversalPreview | null;
    reversalReason: string | null;
    today: string;
}) {
    const isReversal = transfer.reverses_transfer_id !== null;
    const isCompleted =
        transfer.source.status === 'posted' &&
        transfer.destination.status === 'posted';
    const fx = isReversal
        ? transfer.original_execution_fx
        : transfer.execution_fx;
    const relatedId = transfer.reverses_transfer_id ?? transfer.reversal?.id;
    const errors = usePage().props.errors;
    const conflict =
        errors.transfer ||
        errors.expected_source_revision ||
        errors.expected_destination_revision;
    return (
        <>
            <Head title={`Transfer #${transfer.id}`} />
            <div className="flex max-w-6xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={
                            (isReversal
                                ? `Transfer reversal #${transfer.id}`
                                : transfer.source.description) ||
                            `Transfer #${transfer.id}`
                        }
                        description={`Transfer #${transfer.id} · ${team.name}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {isReversal
                                ? 'Reversal'
                                : transfer.reversal
                                  ? 'Reversed'
                                  : isCompleted
                                    ? 'Completed'
                                    : 'Check leg status'}
                        </Badge>
                        {canReverse && reversalPreview ? (
                            <ReverseTransferDialog
                                key={transfer.id}
                                team={team}
                                transfer={transfer}
                                preview={reversalPreview}
                                today={today}
                            />
                        ) : (
                            <Button variant="outline" disabled>
                                <Undo2 /> Reverse Transfer
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={index(team.slug)}>
                                Transfer history
                            </Link>
                        </Button>
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">
                    Recorded {formatUtcTimestamp(transfer.recorded_at)} by{' '}
                    {transfer.creator?.name || 'System / former user'}. Both
                    booked legs are retained. Completed transfers are immutable;
                    corrections create a linked reversal.
                </p>
                {conflict && (
                    <div
                        role="alert"
                        className="grid gap-3 rounded-md border border-destructive/50 bg-card p-4"
                    >
                        <InputError message={conflict} />
                        <p className="text-sm">
                            Your request has not been retried. Reload the
                            accepted transfer before continuing.
                        </p>
                        <Link
                            className="text-sm underline underline-offset-4"
                            href={show([team.slug, transfer.id])}
                            preserveState={false}
                        >
                            Reload latest transfer
                        </Link>
                    </div>
                )}
                {relatedId !== undefined && (
                    <section
                        className="grid gap-2 rounded-md border bg-card p-4"
                        aria-label="Transfer correction"
                    >
                        <h2 className="font-medium">
                            {isReversal
                                ? 'Reverses an original transfer'
                                : 'This transfer has been reversed'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            The original and both reversal movements remain in
                            history with their audit records.
                        </p>
                        {reversalReason && (
                            <p className="text-sm">Reason: {reversalReason}</p>
                        )}
                        <Link
                            className="underline underline-offset-4"
                            href={show([team.slug, relatedId])}
                        >
                            {isReversal
                                ? `View original transfer #${relatedId}`
                                : `View reversal #${relatedId}`}
                        </Link>
                    </section>
                )}
                {!canReverse && (
                    <p className="text-sm text-muted-foreground">
                        {isReversal
                            ? 'Reversal groups cannot be reversed again.'
                            : transfer.reversal
                              ? 'This transfer already has a reversal.'
                              : transfer.source.account.archived_at ||
                                  transfer.destination.account.archived_at
                                ? 'Archived accounts retain read-only transfer history.'
                                : !isCompleted
                                  ? 'Only completed posted transfers can be reversed.'
                                  : 'Owners and admins can reverse eligible completed transfers.'}
                    </p>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    <TransferLegPanel
                        leg={transfer.source}
                        source
                        team={team}
                    />
                    <TransferLegPanel
                        leg={transfer.destination}
                        source={false}
                        team={team}
                    />
                </div>
                <section className="grid gap-3 rounded-md border bg-card p-6">
                    <h2 className="font-medium">
                        {isReversal
                            ? 'Original execution FX (historical)'
                            : 'Execution FX provenance'}
                    </h2>
                    {fx ? (
                        <>
                            <p className="break-all tabular-nums">
                                1 {fx.base_currency_code} ={' '}
                                {formatDecimal(fx.rate, fx.quote_currency_code)}{' '}
                                {fx.quote_currency_code}
                            </p>
                            <p className="text-sm">Source: {fx.source}</p>
                            <p className="text-sm">
                                Effective:{' '}
                                {fx.effective_at
                                    ? formatUtcTimestamp(fx.effective_at)
                                    : 'Not recorded'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {isReversal
                                    ? 'This historical quote belongs to the original transfer. The reversal negates the original booked amounts; no new FX execution or conversion occurred.'
                                    : 'This is the recorded execution quote. The two booked amounts above remain authoritative.'}
                            </p>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {isReversal
                                ? 'No new FX execution occurred. The reversal uses the exact original booked amounts, without inferring an exchange rate.'
                                : 'No execution FX quote was recorded. No rate has been inferred from these amounts.'}
                        </p>
                    )}
                </section>
                <p className="text-sm text-muted-foreground">
                    Transfer movements are excluded from income and expense
                    analytics. Account balance observations are unchanged.
                </p>
            </div>
        </>
    );
}
