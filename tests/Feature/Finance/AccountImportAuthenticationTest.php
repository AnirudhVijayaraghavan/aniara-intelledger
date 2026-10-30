<?php

declare(strict_types=1);

use App\Actions\Finance\CreateFinancialAccount;
use App\Actions\Finance\StoreEncryptedImportFile;
use App\Actions\Finance\UpdateFinancialAccount;
use App\Actions\Finance\UploadTransactionImport;
use App\Enums\TeamRole;
use App\Http\Controllers\Finance\FinancialAccountController;
use App\Http\Controllers\Finance\TransactionImportController;
use App\Http\Requests\Finance\ArchiveFinancialAccountRequest;
use App\Http\Requests\Finance\SaveFinancialAccountRequest;
use App\Http\Requests\Finance\UploadTransactionImportRequest;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

function accountImportPrincipalCall(string $operation, mixed $principal, Team $team, FinancialAccount $account): Closure
{
    $input = [
        'name' => 'Request account', 'type' => $account->type->value, 'currency_code' => $account->currency_code,
        'financial_institution_id' => null,
        'owners' => $operation === 'update'
            ? $account->owners()->get()->map(fn ($owner) => $owner->only(['id', 'owner_name', 'user_id']))->all()
            : [['owner_name' => 'Recorded holder', 'user_id' => null]],
    ];
    $request = match ($operation) {
        'store', 'update' => new SaveFinancialAccountRequest($input),
        'archive' => new ArchiveFinancialAccountRequest,
        'import' => new UploadTransactionImportRequest,
        default => Request::create('/'.$team->slug.'/accounts', 'GET', [], [], [], ['HTTP_X_INERTIA' => 'true']),
    };
    $request->setUserResolver(fn () => $principal);
    if ($request instanceof SaveFinancialAccountRequest) {
        $route = new Route('POST', '{current_team}/accounts', fn () => null);
        $route->bind(Request::create('/'.$team->slug.'/accounts', 'POST'));
        $route->setParameter('current_team', $team);
        if ($operation === 'update') {
            $route->setParameter('financial_account', $account);
        }
        $request->setRouteResolver(fn () => $route);
        $validator = Validator::make($input, $request->rules());
        $request->withValidator($validator);
        $request->setValidator($validator);
    }
    if ($operation === 'import') {
        $request->files->set('file', UploadedFile::fake()->createWithContent('statement.csv', "Date,Amount\n2026-10-01,10\n"));

        return fn () => app(TransactionImportController::class)->store($request, $team, $account, app(UploadTransactionImport::class));
    }
    $controller = app(FinancialAccountController::class);
    $call = match ($operation) {
        'index', 'create' => fn () => $controller->{$operation}($request, $team),
        'show', 'edit', 'archive' => fn () => $controller->{$operation}($request, $team, $account),
        'store' => fn () => $controller->store($request, $team, app(CreateFinancialAccount::class)),
        'update' => fn () => $controller->update($request, $team, $account, app(UpdateFinancialAccount::class)),
    };

    return in_array($operation, ['index', 'show'], true)
        ? fn () => $call()->toResponse($request)
        : $call;
}

test('account and private import boundaries reject missing or incompatible request principals despite a valid global owner', function (string $operation, bool $missing) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $before = $account->fresh()->getAttributes();
    $holders = $account->owners()->get()->toArray();
    $this->actingAs($owner);
    Storage::fake(StoreEncryptedImportFile::DISK);
    $principal = $missing ? null : new GenericUser(['id' => $owner->id]);

    expect(accountImportPrincipalCall($operation, $principal, $team, $account))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');

    expect($account->fresh()->getAttributes())->toBe($before);
    expect($account->owners()->get()->toArray())->toBe($holders);
    $this->assertDatabaseCount('financial_accounts', 1);
    $this->assertDatabaseCount('transaction_import_batches', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
})->with(['index', 'create', 'store', 'show', 'edit', 'update', 'archive', 'import'])->with([false, true]);

test('account mutations and import staging authorize the request member or outsider rather than the privileged global owner', function (string $operation, bool $member) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $before = $account->fresh()->getAttributes();
    $holders = $account->owners()->get()->toArray();
    $this->actingAs($owner);
    Storage::fake(StoreEncryptedImportFile::DISK);

    expect(accountImportPrincipalCall($operation, $actor, $team, $account))->toThrow(AuthorizationException::class);

    expect($account->fresh()->getAttributes())->toBe($before);
    expect($account->owners()->get()->toArray())->toBe($holders);
    $this->assertDatabaseCount('financial_accounts', 1);
    $this->assertDatabaseCount('transaction_import_batches', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
})->with(['create', 'edit', 'store', 'update', 'archive', 'import'])->with([false, true]);

test('account read boundaries reject a request outsider despite an authorized global owner', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $this->actingAs($owner);

    expect(accountImportPrincipalCall($operation, User::factory()->create(), $team, $account))->toThrow(AuthorizationException::class);
})->with(['index', 'show']);

test('member account read responses retain request specific permissions despite a privileged global owner', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $this->actingAs($owner);

    $props = accountImportPrincipalCall($operation, $member, $team, $account)()->getData(true)['props'];

    expect($props['team']['id'])->toBe($team->id);
    expect($props['canManage'])->toBeFalse();
    if ($operation === 'show') {
        expect($props['account']['id'])->toBe($account->id);
        expect($props['canRecordBalance'])->toBeFalse();
        expect($props['canVoidBalances'])->toBeFalse();
    }
})->with(['index', 'show']);

test('account creation and CSV staging record the explicit request actor rather than an unrelated global user', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $this->actingAs(User::factory()->create());
    Storage::fake(StoreEncryptedImportFile::DISK);

    accountImportPrincipalCall($operation, $owner, $team, $account)();

    if ($operation === 'store') {
        $this->assertDatabaseHas('financial_accounts', ['team_id' => $team->id, 'name' => 'Request account', 'created_by' => $owner->id]);
    } else {
        $batch = TransactionImportBatch::sole();
        expect($batch->team_id)->toBe($team->id);
        expect($batch->financial_account_id)->toBe($account->id);
        expect($batch->uploaded_by)->toBe($owner->id);
        expect($batch->total_rows)->toBe(1);
    }
    $this->assertDatabaseCount('account_balances', 0);
    $this->assertDatabaseCount('account_transactions', 0);
})->with(['store', 'import']);
