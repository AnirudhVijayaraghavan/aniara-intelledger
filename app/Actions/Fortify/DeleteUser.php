<?php

namespace App\Actions\Fortify;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    /**
     * Delete the user without leaving active teams without an owner.
     *
     * Locking the user first prevents new memberships during deletion. Team
     * locks serialize ownership checks with membership and team mutations.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $teamIds = $user->teamMemberships()->pluck('team_id');
            $teams = Team::whereIn('id', $teamIds)->orderBy('id')->lockForUpdate()->get();
            $personalTeams = [];

            foreach ($teams as $team) {
                $memberships = $team->memberships()->orderBy('id')->lockForUpdate()->get();
                $membership = $memberships->firstWhere('user_id', $user->id);

                if ($membership?->role !== TeamRole::Owner) {
                    continue;
                }

                $hasAnotherOwner = $memberships->contains(
                    fn (Membership $member): bool => $member->role === TeamRole::Owner && $member->user_id !== $user->id,
                );

                if ($hasAnotherOwner) {
                    continue;
                }

                if (! $team->is_personal || $memberships->count() !== 1) {
                    throw ValidationException::withMessages([
                        'password' => __('You must transfer ownership or delete your shared teams before deleting your account.'),
                    ]);
                }

                $personalTeams[] = $team;
            }

            foreach ($personalTeams as $team) {
                $team->invitations()->delete();
                $team->memberships()->delete();
                $team->delete();
            }

            $user->delete();
        }, attempts: 3);
    }
}
