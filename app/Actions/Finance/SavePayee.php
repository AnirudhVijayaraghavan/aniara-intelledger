<?php

namespace App\Actions\Finance;

use App\Models\Payee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SavePayee
{
    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'team_id' => ['prohibited'], 'id' => ['prohibited'], 'archived_at' => ['prohibited']];
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, array $input, ?Payee $payee = null): Payee
    {
        return DB::transaction(function () use ($actor, $team, $input, $payee): Payee {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            if ($payee !== null) {
                $payee = $team->payees()->whereKey($payee->id)->lockForUpdate()->firstOrFail();
                $payee->setRelation('team', $team);
                Gate::forUser($actor)->authorize('update', $payee);
            } else {
                Gate::forUser($actor)->authorize('create', [Payee::class, $team]);
            }
            $validated = Validator::make($input, self::rules())->validate();
            $payee ??= $team->payees()->make();
            $payee->name = $validated['name'];
            $payee->saveOrFail();

            return $payee;
        }, attempts: 3);
    }
}
