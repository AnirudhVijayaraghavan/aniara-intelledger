<?php

namespace App\Http\Controllers;

use App\Actions\Finance\ConsolidatedNetWorthCalculator;
use App\Data\ConsolidatedNetWorthResult;
use App\Http\AuthenticatedUserResolver;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function __invoke(Request $request, Team $currentTeam, ConsolidatedNetWorthCalculator $calculator): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('viewAny', [FinancialAccount::class, $currentTeam]);
        $email = strtolower($user->email);
        $valuation = null;
        $calculate = function () use (&$valuation, $calculator, $user, $currentTeam): ConsolidatedNetWorthResult {
            return $valuation ??= $calculator->handle($user, $currentTeam, now()->toDateString());
        };

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'financialOverview' => fn (): array => $calculate()->original->jsonSerialize(),
            'consolidatedOverview' => fn (): array => $calculate()->jsonSerialize(),
            'canManageAccounts' => fn (): bool => Gate::forUser($user)->allows('create', [FinancialAccount::class, $currentTeam]),
            'canManageExchangeRates' => fn (): bool => Gate::forUser($user)->allows('create', [ExchangeRate::class, $currentTeam]),
        ]);
    }
}
