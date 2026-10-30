<?php

namespace App\Actions\Finance;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateTeamReportingCurrency
{
    public const array SUPPORTED_CURRENCIES = ['USD', 'INR'];

    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        return [
            'reporting_currency_code' => ['required', 'string', Rule::in(self::SUPPORTED_CURRENCIES), Rule::exists('currencies', 'code')],
            'team_id' => ['prohibited'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, array $input): Team
    {
        return DB::transaction(function () use ($actor, $team, $input): Team {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('updateReportingCurrency', $team);
            $validated = Validator::make($input, self::rules())->validate();
            $team->update(['reporting_currency_code' => $validated['reporting_currency_code']]);

            return $team;
        }, attempts: 3);
    }
}
