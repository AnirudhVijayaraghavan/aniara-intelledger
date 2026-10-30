<?php

declare(strict_types=1);

use App\Actions\Finance\CancelTransaction;
use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\PostTransaction;
use App\Actions\Finance\RecordExchangeRate;
use App\Actions\Finance\ReverseTransaction;
use App\Actions\Finance\ReverseTransfer;
use App\Enums\TeamRole;
use App\Http\Controllers\Finance\ExchangeRateController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\TransferController;
use App\Http\Requests\Finance\CancelTransactionRequest;
use App\Http\Requests\Finance\ClassifyTransactionRequest;
use App\Http\Requests\Finance\CreateTransactionRequest;
use App\Http\Requests\Finance\CreateTransferRequest;
use App\Http\Requests\Finance\ListTransactionsRequest;
use App\Http\Requests\Finance\ListTransfersRequest;
use App\Http\Requests\Finance\PostTransactionRequest;
use App\Http\Requests\Finance\RecordExchangeRateRequest;
use App\Http\Requests\Finance\ReverseTransactionRequest;
use App\Http\Requests\Finance\ReverseTransferRequest;
use App\Models\AccountTransaction;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionTransfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** @return array{User, Team, FinancialAccount, AccountTransaction, TransactionTransfer} */
function financialControllerContext(string $operation): array
{
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $factory = AccountTransaction::factory();
    if ($operation === 'transactions.reverse') {
        $factory = $factory->posted();
    }
    $transaction = $factory->create([
        'financial_account_id' => $account->id, 'transaction_date' => '2026-10-01',
        'posted_date' => $operation === 'transactions.reverse' ? '2026-10-02' : null,
    ]);
    $transfer = TransactionTransfer::factory()->create(['team_id' => $team->id, 'source_account_id' => $account->id]);

    return [$owner, $team, $account, $transaction, $transfer];
}

/** @return array<string, string> */
function financialControllerState(): array
{
    $state = [];
    foreach (['account_transactions', 'transaction_revisions', 'transaction_transfers', 'exchange_rates', 'account_balances'] as $table) {
        $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }

    return $state;
}

function financialControllerCall(string $operation, mixed $principal, Team $team, FinancialAccount $account, AccountTransaction $transaction, TransactionTransfer $transfer, bool $validate = true): Closure
{
    $input = match ($operation) {
        'fx.store' => ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83.123456789012345678', 'effective_at' => '2026-10-01T12:00:00.123456Z'],
        'transactions.store' => ['financial_account_id' => $account->id, 'kind' => 'expense', 'amount' => '-10.12345678', 'transaction_date' => '2026-10-01', 'status' => 'pending'],
        'transactions.post', 'transactions.reverse' => ['expected_revision' => 1, 'posted_date' => '2026-10-03', 'reason' => 'Manually confirmed'],
        'transactions.cancel' => ['expected_revision' => 1],
        'transactions.classify' => ['expected_revision' => 1, 'kind' => 'expense', 'excluded_from_analytics' => true],
        'transfers.store' => ['source_account_id' => $account->id, 'destination_account_id' => $transfer->destination_account_id, 'source_amount' => '10.12345678', 'destination_amount' => '10.12345678', 'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02'],
        'transfers.reverse' => ['expected_source_revision' => 1, 'expected_destination_revision' => 1, 'posted_date' => '2026-10-03', 'reason' => 'Manually confirmed'],
        default => [],
    };
    $requestClass = match ($operation) {
        'fx.store' => RecordExchangeRateRequest::class,
        'transactions.index' => ListTransactionsRequest::class,
        'transactions.store' => CreateTransactionRequest::class,
        'transactions.post' => PostTransactionRequest::class,
        'transactions.cancel' => CancelTransactionRequest::class,
        'transactions.classify' => ClassifyTransactionRequest::class,
        'transactions.reverse' => ReverseTransactionRequest::class,
        'transfers.index' => ListTransfersRequest::class,
        'transfers.store' => CreateTransferRequest::class,
        'transfers.reverse' => ReverseTransferRequest::class,
        default => Request::class,
    };
    $request = new $requestClass($input);
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $principal);
    $route = new Route('GET', 'financial-test', fn () => null);
    $route->bind(Request::create('/financial-test'));
    foreach (['current_team' => $team, 'account_transaction' => $transaction, 'transaction_transfer' => $transfer] as $key => $value) {
        $route->setParameter($key, $value);
    }
    $request->setRouteResolver(fn () => $route);
    if ($request instanceof FormRequest) {
        $request->setContainer(app())->setRedirector(app('redirect'));
        if ($validate) {
            $request->setValidator(Validator::make($request->validationData(), $request->rules()));
        }
    }
    [$family, $method] = explode('.', $operation);
    $controller = match ($family) {
        'fx' => app(ExchangeRateController::class),
        'transactions' => app(TransactionController::class),
        'transfers' => app(TransferController::class),
    };
    $call = match ($operation) {
        'fx.index', 'transactions.index', 'transactions.create', 'transfers.index', 'transfers.create' => fn () => $controller->{$method}($request, $team),
        'fx.store' => fn () => $controller->store($request, $team, app(RecordExchangeRate::class)),
        'transactions.show' => fn () => $controller->show($request, $team, $transaction),
        'transactions.store' => fn () => $controller->store($request, $team, app(CreateTransaction::class)),
        'transactions.post' => fn () => $controller->post($request, $team, $transaction, app(PostTransaction::class)),
        'transactions.cancel' => fn () => $controller->cancel($request, $team, $transaction, app(CancelTransaction::class)),
        'transactions.classify' => fn () => $controller->classify($request, $team, $transaction, app(ClassifyTransaction::class)),
        'transactions.reverse' => fn () => $controller->reverse($request, $team, $transaction, app(ReverseTransaction::class)),
        'transfers.show' => fn () => $controller->show($request, $team, $transfer, app(ReverseTransfer::class)),
        'transfers.store' => fn () => $controller->store($request, $team, app(CreateTransfer::class)),
        'transfers.reverse' => fn () => $controller->reverse($request, $team, $transfer, app(ReverseTransfer::class)),
    };

    return in_array($method, ['index', 'show'], true) ? fn () => $call()->toResponse($request) : $call;
}

test('financial controller boundaries reject missing or incompatible request principals despite a valid global owner', function (string $operation, bool $missing) {
    [$owner, $team, $account, $transaction, $transfer] = financialControllerContext($operation);
    $before = financialControllerState();
    $this->actingAs($owner);
    $principal = $missing ? null : new GenericUser(['id' => $owner->id]);

    expect(financialControllerCall($operation, $principal, $team, $account, $transaction, $transfer, false))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');

    expect(financialControllerState())->toBe($before);
})->with(['fx.index', 'fx.store', 'transactions.index', 'transactions.create', 'transactions.show', 'transactions.store', 'transactions.post', 'transactions.cancel', 'transactions.classify', 'transactions.reverse', 'transfers.index', 'transfers.create', 'transfers.show', 'transfers.store', 'transfers.reverse'])->with([false, true]);

test('financial mutations deny the request member or outsider despite a privileged global owner', function (string $operation, bool $member) {
    [$owner, $team, $account, $transaction, $transfer] = financialControllerContext($operation);
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    $before = financialControllerState();
    $this->actingAs($owner);

    expect(financialControllerCall($operation, $actor, $team, $account, $transaction, $transfer))->toThrow(AuthorizationException::class);

    expect(financialControllerState())->toBe($before);
})->with(['fx.store', 'transactions.store', 'transactions.post', 'transactions.cancel', 'transactions.classify', 'transactions.reverse', 'transfers.store', 'transfers.reverse'])->with([false, true]);

test('financial read permissions belong to the request member rather than the privileged global guard', function (string $operation) {
    [$owner, $team, $account, $transaction, $transfer] = financialControllerContext($operation);
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $this->actingAs($owner);
    $before = financialControllerState();

    $props = financialControllerCall($operation, $member, $team, $account, $transaction, $transfer)()->getData(true)['props'];

    expect($props['team']['id'])->toBe($team->id);
    foreach (['canRecord', 'canCreate', 'canCreateTransfer', 'canManage', 'canReverse'] as $flag) {
        if (array_key_exists($flag, $props)) {
            expect($props[$flag])->toBeFalse();
        }
    }
    expect(financialControllerState())->toBe($before);
})->with(['fx.index', 'transactions.index', 'transactions.show', 'transfers.index', 'transfers.show']);

test('financial read endpoints deny an outsider request even when the global owner can read the household', function (string $operation) {
    [$owner, $team, $account, $transaction, $transfer] = financialControllerContext($operation);
    $this->actingAs($owner);

    expect(financialControllerCall($operation, User::factory()->create(), $team, $account, $transaction, $transfer))->toThrow(AuthorizationException::class);
})->with(['fx.index', 'transactions.index', 'transactions.show', 'transfers.index', 'transfers.show']);

test('financial creations and their audit records use the authorized request user while the global guard belongs elsewhere', function (string $operation) {
    [$owner, $team, $account, $transaction, $transfer] = financialControllerContext($operation);
    $this->actingAs(User::factory()->create());

    financialControllerCall($operation, $owner, $team, $account, $transaction, $transfer)();

    if ($operation === 'fx.store') {
        expect(ExchangeRate::sole()->created_by)->toBe($owner->id);
    } elseif ($operation === 'transactions.store') {
        $created = AccountTransaction::orderByDesc('id')->firstOrFail();
        expect($created->created_by)->toBe($owner->id);
        expect($created->revisions()->sole()->actor_id)->toBe($owner->id);
    } else {
        $created = TransactionTransfer::orderByDesc('id')->firstOrFail();
        expect($created->created_by)->toBe($owner->id);
        expect($created->sourceTransaction->revisions()->sole()->actor_id)->toBe($owner->id);
        expect($created->destinationTransaction->revisions()->sole()->actor_id)->toBe($owner->id);
    }
    $this->assertDatabaseCount('account_balances', 0);
})->with(['fx.store', 'transactions.store', 'transfers.store']);

test('financial creation routes retain JSON guest authentication responses', function (string $routeName) {
    [$owner, $team] = financialControllerContext('transactions.store');
    $before = financialControllerState();

    $this->postJson(route($routeName, ['current_team' => $team->slug]))->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);

    expect(financialControllerState())->toBe($before);
})->with(['exchange-rates.store', 'transactions.store', 'transfers.store']);
