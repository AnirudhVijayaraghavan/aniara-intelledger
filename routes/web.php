<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\AccountBalanceController;
use App\Http\Controllers\Finance\ExchangeRateController;
use App\Http\Controllers\Finance\FinancialAccountController;
use App\Http\Controllers\Finance\FinancialActivityController;
use App\Http\Controllers\Finance\PayeeController;
use App\Http\Controllers\Finance\TransactionCategoryController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\TransactionImportController;
use App\Http\Controllers\Finance\TransferController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('financial-activity', FinancialActivityController::class)->name('financial-activity.index');
        Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');

        Route::scopeBindings()->group(function () {
            Route::resource('transfers', TransferController::class)->parameters(['transfers' => 'transaction_transfer'])->only(['index', 'create', 'store', 'show']);
            Route::post('transfers/{transaction_transfer}/reverse', [TransferController::class, 'reverse'])->name('transfers.reverse');
            Route::resource('categories', TransactionCategoryController::class)->parameters(['categories' => 'transaction_category'])->only(['index', 'store', 'update']);
            Route::post('categories/{transaction_category}/archive', [TransactionCategoryController::class, 'archive'])->name('categories.archive');
            Route::resource('payees', PayeeController::class)->only(['index', 'store', 'update']);
            Route::post('payees/{payee}/archive', [PayeeController::class, 'archive'])->name('payees.archive');
            Route::resource('transactions', TransactionController::class)
                ->parameters(['transactions' => 'account_transaction'])->only(['index', 'create', 'store', 'show']);
            Route::post('transactions/{account_transaction}/post', [TransactionController::class, 'post'])->name('transactions.post');
            Route::post('transactions/{account_transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');
            Route::post('transactions/{account_transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse');
            Route::patch('transactions/{account_transaction}/classification', [TransactionController::class, 'classify'])->name('transactions.classify');
            Route::resource('accounts', FinancialAccountController::class)
                ->parameters(['accounts' => 'financial_account'])->except('destroy');
            Route::post('accounts/{financial_account}/archive', [FinancialAccountController::class, 'archive'])->name('accounts.archive');
            Route::post('accounts/{financial_account}/imports', [TransactionImportController::class, 'store'])
                ->middleware('throttle:10,1')->name('accounts.imports.store');
            Route::post('accounts/{financial_account}/balances', [AccountBalanceController::class, 'store'])->name('accounts.balances.store');
            Route::post('accounts/{financial_account}/balances/{balance}/void', [AccountBalanceController::class, 'void'])->name('accounts.balances.void');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
