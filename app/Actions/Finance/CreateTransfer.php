<?php

namespace App\Actions\Finance;

use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\ExchangeRate;
use App\Models\Team;
use App\Models\TransactionTransfer;
use App\Models\User;
use App\Rules\BalanceAmount;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateTransfer
{
    public function __construct(private CreateTransaction $createTransaction) {}

    /** @return array<string, array<mixed>> */
    public function rules(Team $team): array
    {
        $positiveAmount = ['bail', 'required', 'string', 'max:64', new BalanceAmount,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! BigDecimal::of($value)->isPositive()) {
                    $fail('Enter a positive booked amount magnitude.');
                }
            }];

        return [
            'source_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('team_id', $team->id)->whereNull('archived_at')],
            'destination_account_id' => ['required', 'integer', 'different:source_account_id', Rule::exists('financial_accounts', 'id')->where('team_id', $team->id)->whereNull('archived_at')],
            'source_amount' => $positiveAmount, 'destination_amount' => $positiveAmount,
            'transaction_date' => ['required', 'date_format:Y-m-d'], 'posted_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:255'], 'reason' => ['nullable', 'string', 'max:2000'],
            'execution_fx' => ['nullable', 'array:base_currency_code,quote_currency_code,rate,source,effective_at', 'required_array_keys:base_currency_code,quote_currency_code,rate,source,effective_at'],
            'execution_fx.base_currency_code' => ['required_with:execution_fx', 'string', Rule::exists('currencies', 'code')],
            'execution_fx.quote_currency_code' => ['required_with:execution_fx', 'string', 'different:execution_fx.base_currency_code', Rule::exists('currencies', 'code')],
            'execution_fx.rate' => ['bail', 'required_with:execution_fx', 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ExchangeRate::normalizeRate($value);
                } catch (InvalidArgumentException|MathException) {
                    $fail('Enter a positive decimal execution rate with at most 18 decimal places.');
                }
            }],
            'execution_fx.source' => ['required_with:execution_fx', 'string', 'max:64'],
            'execution_fx.effective_at' => ['required_with:execution_fx', 'string', 'date', 'regex:/\\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\\.[0-9]{1,6})?(?:Z|[+-][0-9]{2}:[0-9]{2})\\z/'],
            'team_id' => ['prohibited'], 'recorded_at' => ['prohibited'], 'created_by' => ['prohibited'],
            'source_transaction_id' => ['prohibited'], 'destination_transaction_id' => ['prohibited'],
            'currency_code' => ['prohibited'], 'source_currency_code' => ['prohibited'], 'destination_currency_code' => ['prohibited'],
            'status' => ['prohibited'], 'kind' => ['prohibited'], 'excluded_from_analytics' => ['prohibited'],
            'category_id' => ['prohibited'], 'payee_id' => ['prohibited'],
            'reverses_transaction_id' => ['prohibited'], 'replaces_transaction_id' => ['prohibited'],
            'reverses_transfer_id' => ['prohibited'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, array $input): TransactionTransfer
    {
        return DB::transaction(function () use ($actor, $team, $input): TransactionTransfer {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [TransactionTransfer::class, $team]);
            if (isset($input['execution_fx']) && is_array($input['execution_fx'])) {
                foreach (['base_currency_code', 'quote_currency_code'] as $field) {
                    if (isset($input['execution_fx'][$field]) && is_string($input['execution_fx'][$field])) {
                        $input['execution_fx'][$field] = strtoupper($input['execution_fx'][$field]);
                    }
                }
            }
            $validated = Validator::make($input, $this->rules($team))->validate();
            $accounts = $team->financialAccounts()->whereKey([$validated['source_account_id'], $validated['destination_account_id']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $accounts->get($validated['source_account_id']);
            $destination = $accounts->get($validated['destination_account_id']);
            abort_if($source === null || $destination === null, 404);
            foreach ([$source, $destination] as $account) {
                $account->setRelation('team', $team);
                Gate::forUser($actor)->authorize('create', [AccountTransaction::class, $account]);
            }
            $sourceMagnitude = BigDecimal::of($validated['source_amount']);
            $destinationMagnitude = BigDecimal::of($validated['destination_amount']);
            if ($source->currency_code === $destination->currency_code && ! $sourceMagnitude->isEqualTo($destinationMagnitude)) {
                throw ValidationException::withMessages(['destination_amount' => 'Same-currency transfers require equal booked magnitudes.']);
            }
            $fx = $validated['execution_fx'] ?? null;
            if ($fx !== null && ($source->currency_code === $destination->currency_code
                || ! (($fx['base_currency_code'] === $source->currency_code && $fx['quote_currency_code'] === $destination->currency_code)
                    || ($fx['base_currency_code'] === $destination->currency_code && $fx['quote_currency_code'] === $source->currency_code)))) {
                throw ValidationException::withMessages(['execution_fx' => 'Execution FX must describe the two different account currencies.']);
            }
            $common = [
                'kind' => TransactionKind::Transfer->value, 'status' => TransactionStatus::Posted->value,
                'transaction_date' => $validated['transaction_date'], 'posted_date' => $validated['posted_date'],
                'excluded_from_analytics' => true, 'description' => $validated['description'] ?? null, 'reason' => $validated['reason'] ?? null,
            ];
            $sourceLeg = $this->createTransaction->handle($actor, $team, $source, [
                ...$common, 'amount' => $source->type->netWorthContribution((string) $sourceMagnitude->negated()),
            ]);
            $destinationLeg = $this->createTransaction->handle($actor, $team, $destination, [
                ...$common, 'amount' => $destination->type->netWorthContribution((string) $destinationMagnitude),
            ]);
            $transfer = new TransactionTransfer([
                'team_id' => $team->id, 'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
                'source_transaction_id' => $sourceLeg->id, 'destination_transaction_id' => $destinationLeg->id,
                'created_by' => $actor->id, 'recorded_at' => now()->utc(),
                'fx_base_currency_code' => $fx['base_currency_code'] ?? null, 'fx_quote_currency_code' => $fx['quote_currency_code'] ?? null,
                'fx_rate' => $fx['rate'] ?? null, 'fx_source' => $fx['source'] ?? null, 'fx_effective_at' => $fx['effective_at'] ?? null,
            ]);
            if (! $transfer->save()) {
                throw new DomainException('The transfer group could not be recorded.');
            }

            return $transfer->load(['sourceTransaction', 'destinationTransaction']);
        }, attempts: 3);
    }
}
