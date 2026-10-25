<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $team, $user): void {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            Gate::authorize('updateMember', [$team, $user]);

            $team->memberships()
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->update(['role' => TeamRole::from($request->validated('role'))]);
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        DB::transaction(function () use ($team, $user): void {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            Gate::authorize('removeMember', [$team, $user]);

            $team->memberships()
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->delete();

            if ($user->isCurrentTeam($team)) {
                $user->switchTeam($user->personalTeam());
            }
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
