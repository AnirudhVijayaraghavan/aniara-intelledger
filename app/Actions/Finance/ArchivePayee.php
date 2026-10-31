<?php

namespace App\Actions\Finance;

use App\Models\Payee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchivePayee
{
    public function handle(User $actor, Team $team, Payee $payee): Payee
    {
        return DB::transaction(function () use ($actor, $team, $payee): Payee {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $payee = $team->payees()->whereKey($payee->id)->lockForUpdate()->firstOrFail();
            $payee->setRelation('team', $team);
            Gate::forUser($actor)->authorize('archive', $payee);
            if ($payee->getAttribute('archived_at') === null) {
                $payee->archived_at = now()->utc();
                $payee->saveOrFail();
            }

            return $payee;
        }, attempts: 3);
    }
}
