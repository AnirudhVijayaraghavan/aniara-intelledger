<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\FinancialActivityCalculator;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialActivityRequest;
use App\Models\AccountTransaction;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialActivityController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(FinancialActivityRequest $request, Team $currentTeam, FinancialActivityCalculator $calculator): Response
    {
        $activity = $calculator->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated('start_date'), $request->validated('end_date'));
        $currency = $request->validated('currency') ?? (isset($activity->currencies['USD']) ? 'USD' : (isset($activity->currencies['INR']) ? 'INR' : 'USD'));

        return Inertia::render('financial-activity/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'activity' => $activity->jsonSerialize(),
            'filters' => ['start_date' => $activity->startDate, 'end_date' => $activity->endDate, 'currency' => $currency],
            'presets' => $request->presets(),
            'canCreateTransaction' => Gate::allows('createAny', [AccountTransaction::class, $currentTeam]),
        ]);
    }
}
