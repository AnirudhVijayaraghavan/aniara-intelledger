<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RecordAccountBalance;
use App\Actions\Finance\VoidAccountBalance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordAccountBalanceRequest;
use App\Http\Requests\Finance\VoidAccountBalanceRequest;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AccountBalanceController extends Controller
{
    public function store(RecordAccountBalanceRequest $request, Team $currentTeam, FinancialAccount $financialAccount, RecordAccountBalance $recordBalance): RedirectResponse
    {
        $recordBalance->handle($request->user(), $currentTeam, $financialAccount, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Balance observation recorded.')]);

        return to_route('accounts.show', ['current_team' => $currentTeam, 'financial_account' => $financialAccount]);
    }

    public function void(VoidAccountBalanceRequest $request, Team $currentTeam, FinancialAccount $financialAccount, AccountBalance $balance, VoidAccountBalance $voidBalance): RedirectResponse
    {
        $voidBalance->handle($request->user(), $currentTeam, $financialAccount, $balance);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Observation voided. The original record has been preserved.')]);

        return to_route('accounts.show', ['current_team' => $currentTeam, 'financial_account' => $financialAccount]);
    }
}
