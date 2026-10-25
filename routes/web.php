<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\AccountBalanceController;
use App\Http\Controllers\Finance\FinancialAccountController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::resource('accounts', FinancialAccountController::class)
                ->parameters(['accounts' => 'financial_account'])->except('destroy');
            Route::post('accounts/{financial_account}/archive', [FinancialAccountController::class, 'archive'])->name('accounts.archive');
            Route::post('accounts/{financial_account}/balances', [AccountBalanceController::class, 'store'])->name('accounts.balances.store');
            Route::post('accounts/{financial_account}/balances/{balance}/void', [AccountBalanceController::class, 'void'])->name('accounts.balances.void');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
