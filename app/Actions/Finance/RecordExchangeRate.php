<?php

namespace App\Actions\Finance;

use App\Models\ExchangeRate;
use App\Models\Team;
use App\Models\User;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RecordExchangeRate
{
    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        return [
            'base_currency_code' => ['required', 'string', Rule::exists('currencies', 'code')],
            'quote_currency_code' => ['required', 'string', 'different:base_currency_code', Rule::exists('currencies', 'code')],
            'rate' => ['bail', 'required', 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ExchangeRate::normalizeRate($value);
                } catch (InvalidArgumentException|MathException) {
                    $fail('A positive decimal exchange rate with at most 18 decimal places is required.');
                }
            }],
            'effective_at' => ['required', 'string', 'date', 'regex:/\\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\\.[0-9]{1,6})?(?:Z|[+-][0-9]{2}:[0-9]{2})\\z/'],
            'team_id' => ['prohibited'],
            'source' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, array $input): ExchangeRate
    {
        return DB::transaction(function () use ($actor, $team, $input): ExchangeRate {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [ExchangeRate::class, $team]);
            foreach (['base_currency_code', 'quote_currency_code'] as $field) {
                if (isset($input[$field]) && is_string($input[$field])) {
                    $input[$field] = strtoupper($input[$field]);
                }
            }
            $validated = Validator::make($input, self::rules())->validate();

            try {
                return $team->exchangeRates()->create([
                    'base_currency_code' => $validated['base_currency_code'],
                    'quote_currency_code' => $validated['quote_currency_code'],
                    'rate' => $validated['rate'],
                    'effective_at' => $validated['effective_at'],
                    'recorded_at' => now()->utc(),
                    'source' => 'manual',
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['rate' => 'An observation with the same pair and timestamps already exists. Please try recording it again.']);
            }
        }, attempts: 3);
    }
}
