<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser->resolve($request);
        DB::transaction(function () use ($request, $team, $user, $actor): void {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('updateMember', [$team, $user]);

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
    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser->resolve($request);
        DB::transaction(function () use ($team, $user, $actor): void {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('removeMember', [$team, $user]);

            $team->memberships()
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->delete();

            if ($user->isCurrentTeam($team)) {
                $personalTeam = $user->personalTeam();
                if ($personalTeam === null) {
                    throw ValidationException::withMessages([
                        'member' => __('The member has no personal team to switch to.'),
                    ]);
                }

                $user->switchTeam($personalTeam);
            }
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
