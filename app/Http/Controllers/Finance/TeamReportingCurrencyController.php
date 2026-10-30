<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\UpdateTeamReportingCurrency;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateReportingCurrencyRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;

class TeamReportingCurrencyController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function __invoke(UpdateReportingCurrencyRequest $request, Team $team, UpdateTeamReportingCurrency $updateCurrency): Response|RedirectResponse
    {
        $updateCurrency->handle($this->authenticatedUser->resolve($request), $team, $request->validated());

        if ($request->header('X-Inertia') === 'true') {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Reporting currency updated.')]);

            return to_route('teams.edit', ['team' => $team]);
        }

        return response()->noContent();
    }
}
