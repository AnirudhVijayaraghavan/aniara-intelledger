<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Finance\UpdateTeamReportingCurrency;
use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Currency;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class TeamController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    /**
     * Display a listing of the user's teams.
     */
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser->resolve($request);

        return Inertia::render('teams/index', [
            'teams' => $user->toUserTeams(includeCurrent: true),
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $team = $createTeam->handle($this->authenticatedUser->resolve($request), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('view', $team);

        return Inertia::render('teams/edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
                'reportingCurrencyCode' => $team->reporting_currency_code,
            ],
            'members' => $team->members()->get()->map(function (User $member) {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            }),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn (TeamInvitation $invitation): array => $this->invitationData($invitation)),
            'permissions' => $user->toTeamPermissions($team),
            'availableRoles' => TeamRole::assignable(),
            'reportingCurrencies' => Currency::whereIn('code', UpdateTeamReportingCurrency::SUPPORTED_CURRENCIES)->orderBy('code')->get(['code', 'name']),
            'canUpdateReportingCurrency' => Gate::forUser($user)->allows('updateReportingCurrency', $team),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $request->validated('name')]);

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        $user = $this->authenticatedUser->resolve($request);
        abort_unless($user->belongsToTeam($team), 403);

        $user->switchTeam($team);

        return back();
    }

    /**
     * Leave the specified team.
     */
    public function leave(Request $request, Team $team): RedirectResponse
    {
        $user = $this->authenticatedUser->resolve($request);
        DB::transaction(function () use ($user, $team): void {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('leave', $team);

            $fallbackTeam = $user->isCurrentTeam($team)
                ? $user->fallbackTeam($team)
                : null;

            $team->memberships()
                ->where('user_id', $user->id)
                ->delete();

            if ($fallbackTeam) {
                $user->switchTeam($fallbackTeam);
            }
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the team ":name"', ['name' => $team->name])]);

        return to_route('teams.index');
    }

    /**
     * Delete the specified team.
     */
    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $this->authenticatedUser->resolve($request);
        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team): void {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('delete', $team);

            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(function (User $affectedUser): void {
                    $personalTeam = $affectedUser->personalTeam();
                    if ($personalTeam === null) {
                        throw ValidationException::withMessages([
                            'name' => __('A user currently using this team has no personal team to switch to.'),
                        ]);
                    }

                    $affectedUser->switchTeam($personalTeam);
                });

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        }, attempts: 3);

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('teams.index');
    }

    /** @return array<string, string> */
    private function invitationData(TeamInvitation $invitation): array
    {
        $createdAt = $invitation->created_at?->toISOString();
        if ($createdAt === null) {
            throw new UnexpectedValueException('Invitation creation timestamp must be valid.');
        }

        return [
            'code' => $invitation->code, 'email' => $invitation->email,
            'role' => $invitation->role->value, 'role_label' => $invitation->role->label(),
            'created_at' => $createdAt,
        ];
    }
}
