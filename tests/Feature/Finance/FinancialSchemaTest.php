<?php

use App\Actions\Finance\CreateFinancialAccount;
use App\Actions\Fortify\DeleteUser;
use App\Enums\AccountBalanceSource;
use App\Enums\FinancialAccountType;
use App\Models\AccountBalance;
use App\Models\Currency;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;

test('USD and INR references exist and institution names may repeat within a household', function () {
    $team = Team::factory()->create();

    $institutions = FinancialInstitution::factory()->count(2)->create(['team_id' => $team->id, 'name' => 'Chase']);

    expect($institutions)->toHaveCount(2);
    expect(Currency::findOrFail('USD')->minor_units)->toBe(2);
    expect(Currency::findOrFail('INR')->minor_units)->toBe(2);
    expect($team->financialInstitutions)->toHaveCount(2);
});

test('accounts cannot reference institutions in another tenant even outside the creation action', function () {
    $institution = FinancialInstitution::factory()->create();

    expect(fn () => FinancialAccount::factory()->create(['financial_institution_id' => $institution->id]))
        ->toThrow(QueryException::class);
});

test('balance and holder foreign keys reject cross tenant parent accounts', function (string $model) {
    $account = FinancialAccount::factory()->create();
    $otherTeam = Team::factory()->create();

    expect(fn () => $model::factory()->create(['financial_account_id' => $account->id, 'team_id' => $otherTeam->id]))
        ->toThrow(QueryException::class);
})->with([AccountBalance::class, FinancialAccountOwner::class]);

test('corrections append observations with independent economic and recording dates and decimal JSON', function () {
    $account = FinancialAccount::factory()->create();
    $original = AccountBalance::factory()->create([
        'financial_account_id' => $account->id,
        'as_of_date' => '2026-10-01',
        'recorded_at' => '2026-10-02 12:00:00',
        'amount' => '125.12345678',
    ]);

    $correction = AccountBalance::factory()->create([
        'financial_account_id' => $account->id,
        'as_of_date' => '2026-10-01',
        'recorded_at' => '2026-10-03 12:00:00',
        'amount' => '130.25000000',
    ]);

    expect($account->balances)->toHaveCount(2);
    expect($original->fresh()->amount)->toBe('125.12345678');
    expect($correction->fresh()->as_of_date->toDateString())->toBe('2026-10-01');
    expect($correction->fresh()->recorded_at->toDateTimeString())->toBe('2026-10-03 12:00:00');
    expect(json_decode($correction->fresh()->toJson(), true)['amount'])->toBe('130.25000000');
    expect($correction->fresh()->source)->toBe(AccountBalanceSource::Manual);
});

test('observations can be voided without losing their amount or history', function () {
    $actor = User::factory()->create();
    $balance = AccountBalance::factory()->create();

    $balance->update(['voided_at' => now(), 'voided_by' => $actor->id]);

    $this->assertModelExists($balance);
    expect($balance->fresh()->amount)->toBe('100.00000000');
    expect($balance->fresh()->voidedBy->is($actor))->toBeTrue();
    expect(AccountBalance::forTeam($balance->team)->notVoided()->count())->toBe(0);
});

test('observation values and recording times cannot be overwritten', function (array $change) {
    $balance = AccountBalance::factory()->create();

    expect(fn () => $balance->update($change))->toThrow(DomainException::class);

    expect($balance->fresh()->amount)->toBe('100.00000000');
})->with([
    'amount' => [['amount' => '999.00000000']],
    'economic date' => [['as_of_date' => '2020-01-01']],
    'recording date' => [['recorded_at' => '2020-01-01 00:00:00']],
]);

test('balance history cannot be deleted through model operations', function () {
    $balance = AccountBalance::factory()->create();

    expect(fn () => $balance->delete())->toThrow(DomainException::class);
    $this->assertModelExists($balance);
});

test('archiving retains accounts and their historical observations', function () {
    $balance = AccountBalance::factory()->create();
    $account = $balance->account;

    $account->update(['archived_at' => now()]);

    expect(FinancialAccount::forTeam($account->team)->findOrFail($account->id)->archived_at)->not->toBeNull();
    expect($account->fresh()->balances)->toHaveCount(1);
});

test('currency and economic classification cannot change after observations', function (array $change) {
    $balance = AccountBalance::factory()->create();
    $account = $balance->account;

    expect(fn () => $account->update($change))->toThrow(DomainException::class);
    expect($account->fresh()->currency_code)->toBe('USD');
    expect($account->fresh()->type)->toBe(FinancialAccountType::Checking);
})->with([
    'currency' => [['currency_code' => 'INR']],
    'classification' => [['type' => FinancialAccountType::Loan]],
]);

test('removing memberships and deleting a user retains financial history and recorded holder identity', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $account = app(CreateFinancialAccount::class)->handle($user, $team, [
        'name' => 'Personal savings', 'type' => 'savings', 'currency_code' => 'USD',
        'owners' => [['owner_name' => 'Recorded holder', 'user_id' => $user->id]],
    ]);
    $balance = AccountBalance::factory()->create([
        'financial_account_id' => $account->id, 'created_by' => $user->id,
        'voided_by' => $user->id, 'voided_at' => now(),
    ]);

    app(DeleteUser::class)->handle($user);

    $this->assertModelMissing($user);
    $this->assertSoftDeleted($team);
    $this->assertModelExists($account);
    $this->assertModelExists($balance);
    expect($balance->fresh()->created_by)->toBeNull();
    expect($balance->fresh()->voided_by)->toBeNull();
    expect($account->fresh()->created_by)->toBeNull();
    expect($account->fresh()->owners->sole()->user_id)->toBeNull();
    expect($account->fresh()->owners->sole()->owner_name)->toBe('Recorded holder');
});

test('hard deletion of a tenant containing finance records is restricted', function () {
    $account = FinancialAccount::factory()->create();

    expect(fn () => $account->team->forceDelete())->toThrow(QueryException::class);
    $this->assertModelExists($account);
});

test('explicit tenant scopes isolate accounts balances institutions and holders', function () {
    $first = FinancialAccount::factory()->create();
    $second = FinancialAccount::factory()->create();
    $balance = AccountBalance::factory()->create(['financial_account_id' => $second->id]);
    $institution = FinancialInstitution::factory()->create(['team_id' => $second->team_id]);

    expect(FinancialAccount::forTeam($first->team)->find($second->id))->toBeNull();
    expect(AccountBalance::forTeam($first->team)->find($balance->id))->toBeNull();
    expect(FinancialInstitution::forTeam($first->team)->find($institution->id))->toBeNull();
    expect(FinancialAccountOwner::forTeam($first->team)->where('financial_account_id', $second->id)->count())->toBe(0);
});

test('accounts cannot reference missing currencies', function () {
    expect(fn () => FinancialAccount::factory()->create(['currency_code' => 'ZZZ']))
        ->toThrow(QueryException::class);
});

test('currencies and institutions referenced by accounts cannot be deleted', function (string $parent) {
    $institution = FinancialInstitution::factory()->create();
    $account = FinancialAccount::factory()->create([
        'team_id' => $institution->team_id,
        'financial_institution_id' => $institution->id,
    ]);
    $record = $parent === 'currency' ? $account->currency : $institution;

    expect(fn () => $record->delete())->toThrow(QueryException::class);
    $this->assertModelExists($account);
})->with(['currency', 'institution']);

test('financial parent records cannot be reassigned to another household', function (string $model) {
    $record = $model::factory()->create();
    $otherTeam = Team::factory()->create();

    expect(fn () => $record->update(['team_id' => $otherTeam->id]))->toThrow(DomainException::class);
    expect($record->fresh()->team_id)->not->toBe($otherTeam->id);
})->with([FinancialInstitution::class, FinancialAccount::class, FinancialAccountOwner::class]);
