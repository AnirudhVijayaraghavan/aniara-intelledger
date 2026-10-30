<?php

namespace App\Actions\Finance;

use App\Concerns\InteractsWithAccountTransactions;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\Team;
use App\Models\TransactionTransfer;
use App\Models\User;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReverseTransfer
{
    use InteractsWithAccountTransactions;

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return array_replace(array_fill_keys([
            'id', 'team_id', 'source_account_id', 'destination_account_id', 'source_transaction_id', 'destination_transaction_id',
            'source_amount', 'destination_amount', 'amount', 'currency_code', 'kind', 'status', 'excluded_from_analytics',
            'transaction_date', 'recorded_at', 'created_by', 'category_id', 'payee_id', 'description', 'execution_fx',
            'fx_rate', 'fx_base_currency_code', 'fx_quote_currency_code', 'fx_source', 'fx_effective_at',
            'reverses_transfer_id', 'reverses_transaction_id', 'replaces_transaction_id',
        ], ['prohibited']), [
            'reason' => ['required', 'string', 'max:2000'],
            'posted_date' => ['required', 'date_format:Y-m-d'],
            'expected_source_revision' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'expected_destination_revision' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);
    }

    /**
     * Amounts are keyed by the original leg roles, before the reversal swaps direction.
     *
     * @return array{source_amount: string, destination_amount: string, minimum_posted_date: string}
     */
    public function preview(TransactionTransfer $transfer): array
    {
        return [
            'source_amount' => $this->oppositeAmount($transfer->sourceTransaction),
            'destination_amount' => $this->oppositeAmount($transfer->destinationTransaction),
            'minimum_posted_date' => max($transfer->sourceTransaction->posted_date?->toDateString() ?? '', $transfer->destinationTransaction->posted_date?->toDateString() ?? ''),
        ];
    }

    private function oppositeAmount(AccountTransaction $leg): string
    {
        return (string) BigDecimal::of($leg->amount)->negated();
    }

    /**
     * Revision tokens refer to the original group's source and destination legs.
     * The reversal's source is the original destination, preserving economic direction.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Team $team, TransactionTransfer $transfer, array $input): TransactionTransfer
    {
        return DB::transaction(function () use ($actor, $team, $transfer, $input): TransactionTransfer {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('viewAny', [TransactionTransfer::class, $team]);
            $original = $team->transactionTransfers()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $original->setRelation('team', $team);
            Gate::forUser($actor)->authorize('reverse', $original);
            $validated = Validator::make($input, $this->rules())->validate();

            if ($original->reverses_transfer_id !== null || $original->reversal()->exists()) {
                throw ValidationException::withMessages(['transfer' => 'This transfer has already been reversed or is itself a reversal. Reload it before continuing.']);
            }
            $accounts = $team->financialAccounts()->whereKey([$original->source_account_id, $original->destination_account_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($accounts as $account) {
                $account->setRelation('team', $team);
                Gate::forUser($actor)->authorize('create', [AccountTransaction::class, $account]);
            }
            $legs = AccountTransaction::forTeam($team)->whereKey([$original->source_transaction_id, $original->destination_transaction_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $legs->get($original->source_transaction_id);
            $destination = $legs->get($original->destination_transaction_id);
            abort_if($source === null || $destination === null || $accounts->count() !== 2, 404);
            foreach (['source' => $source, 'destination' => $destination] as $role => $leg) {
                $latest = $leg->revisions()->reorder()->orderByDesc('revision_number')->lockForUpdate()->firstOrFail();
                if ((int) $validated['expected_'.$role.'_revision'] !== $latest->revision_number) {
                    throw ValidationException::withMessages(['expected_'.$role.'_revision' => 'This transfer leg has changed. Reload the transfer before trying again.']);
                }
                if ($leg->status !== TransactionStatus::Posted || $leg->posted_date === null) {
                    throw ValidationException::withMessages(['transfer' => 'Only completed posted transfers can be reversed.']);
                }
                if ($validated['posted_date'] < $leg->posted_date->toDateString()) {
                    throw ValidationException::withMessages(['posted_date' => 'The reversal date cannot precede either original posting date.']);
                }
            }
            $newSource = $this->reverseLeg($actor, $team, $destination, $validated['posted_date'], trim($validated['reason']));
            $newDestination = $this->reverseLeg($actor, $team, $source, $validated['posted_date'], trim($validated['reason']));
            $reversal = new TransactionTransfer([
                'team_id' => $team->id, 'reverses_transfer_id' => $original->id,
                'source_account_id' => $original->destination_account_id, 'destination_account_id' => $original->source_account_id,
                'source_transaction_id' => $newSource->id, 'destination_transaction_id' => $newDestination->id,
                'created_by' => $actor->id, 'recorded_at' => now()->utc(),
            ]);
            if (! $reversal->save()) {
                throw new DomainException('The transfer reversal group could not be recorded.');
            }

            return $reversal->load(['sourceTransaction', 'destinationTransaction', 'reversesTransfer']);
        }, attempts: 3);
    }

    private function reverseLeg(User $actor, Team $team, AccountTransaction $original, string $date, string $reason): AccountTransaction
    {
        $reversal = new AccountTransaction([
            'team_id' => $team->id, 'financial_account_id' => $original->financial_account_id,
            'currency_code' => $original->currency_code, 'amount' => $this->oppositeAmount($original),
            'kind' => $original->kind, 'status' => TransactionStatus::Posted,
            'transaction_date' => $date, 'posted_date' => $date, 'description' => $original->description,
            'excluded_from_analytics' => $original->excluded_from_analytics, 'reverses_transaction_id' => $original->id,
            'category_id' => $original->category_id, 'payee_id' => $original->payee_id,
            'created_by' => $actor->id, 'recorded_at' => now()->utc(),
        ]);

        return $this->saveTransaction($reversal, $actor, $reason);
    }
}
