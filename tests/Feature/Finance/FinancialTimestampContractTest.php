<?php

declare(strict_types=1);

use App\Actions\Finance\ReverseTransfer;
use App\Models\AccountTransaction;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;

test('FX history rejects a missing or invalid hydrated required timestamp without rewriting stored history', function (string $field, ?string $value) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $rate = ExchangeRate::factory()->create(['team_id' => $team->id]);
    $stored = DB::table('exchange_rates')->where('id', $rate->id)->value($field);
    Exceptions::fake();
    Event::listen('eloquent.retrieved: '.ExchangeRate::class, function (ExchangeRate $record) use ($field, $value): void {
        $record->setRawAttributes(array_replace($record->getAttributes(), [$field => $value]));
    });

    $this->actingAs($owner)->getJson(route('exchange-rates.index', ['current_team' => $team->slug]))->assertInternalServerError();

    Exceptions::assertReported(fn (UnexpectedValueException $exception): bool => $exception->getMessage() === 'Stored exchange-rate timestamps must be valid.');
    expect(DB::table('exchange_rates')->where('id', $rate->id)->value($field))->toBe($stored);
})->with(['effective_at', 'recorded_at'])->with([null, '0000-01-01 00:00:00.000000']);

test('transaction history rejects missing or invalid hydrated revision timestamps without changing the audit', function (?string $value) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $revision = $transaction->revisions()->sole();
    $stored = DB::table('transaction_revisions')->where('id', $revision->id)->value('recorded_at');
    Exceptions::fake();
    Event::listen('eloquent.retrieved: '.TransactionRevision::class, function (TransactionRevision $record) use ($value): void {
        $record->setRawAttributes(array_replace($record->getAttributes(), ['recorded_at' => $value]));
    });

    $this->actingAs($owner)->getJson(route('transactions.show', ['current_team' => $team->slug, 'account_transaction' => $transaction->id]))->assertInternalServerError();

    Exceptions::assertReported(fn (UnexpectedValueException $exception): bool => $exception->getMessage() === 'Transaction revision recording timestamp must be valid.');
    expect(DB::table('transaction_revisions')->where('id', $revision->id)->value('recorded_at'))->toBe($stored);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with([null, '0000-01-01 00:00:00.000000']);

test('transfer history rejects missing or invalid hydrated group recording timestamps without changing booked legs', function (bool $reversalTimestamp, ?string $value) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $transfer = TransactionTransfer::factory()->create(['team_id' => $team->id]);
    $target = $reversalTimestamp
        ? app(ReverseTransfer::class)->handle($owner, $team, $transfer, [
            'expected_source_revision' => 1, 'expected_destination_revision' => 1,
            'posted_date' => now()->toDateString(), 'reason' => 'Correct original transfer',
        ])
        : $transfer;
    $stored = DB::table('transaction_transfers')->where('id', $target->id)->value('recorded_at');
    $legsBefore = DB::table('account_transactions')->orderBy('id')->get()->toJson();
    Exceptions::fake();
    Event::listen('eloquent.retrieved: '.TransactionTransfer::class, function (TransactionTransfer $record) use ($target, $value): void {
        if ($record->id === $target->id) {
            $record->setRawAttributes(array_replace($record->getAttributes(), ['recorded_at' => $value]));
        }
    });

    $this->actingAs($owner)->getJson(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id]))->assertInternalServerError();

    Exceptions::assertReported(fn (UnexpectedValueException $exception): bool => $exception->getMessage() === 'Transfer recording timestamps must be valid.');
    expect(DB::table('transaction_transfers')->where('id', $target->id)->value('recorded_at'))->toBe($stored);
    expect(DB::table('account_transactions')->orderBy('id')->get()->toJson())->toBe($legsBefore);
})->with([false, true])->with([null, '0000-01-01 00:00:00.000000']);

test('FX history preserves UTC microseconds and original rate provenance for valid timestamps', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $rate = ExchangeRate::factory()->create([
        'team_id' => $team->id, 'rate' => '83.123456789012345678',
        'effective_at' => '2026-10-01T12:00:00.123456+05:30',
        'recorded_at' => '2026-10-02T02:00:00.654321-04:00',
    ]);

    $this->actingAs($owner)->get(route('exchange-rates.index', ['current_team' => $team->slug]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('rates.data.0.id', $rate->id)
            ->where('rates.data.0.effectiveAt', '2026-10-01T06:30:00.123456Z')
            ->where('rates.data.0.recordedAt', '2026-10-02T06:00:00.654321Z')
            ->where('rates.data.0.rate', '83.123456789012345678')->where('rates.data.0.source', 'manual'));
});

test('transaction audit serialization preserves recording microseconds', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01T06:30:00.123456Z'));
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id, 'recorded_at' => '2026-10-01T12:00:00.123456+05:30']);

    $this->actingAs($owner)->get(route('transactions.show', ['current_team' => $team->slug, 'account_transaction' => $transaction->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('revisions.data.0.recorded_at', '2026-10-01T06:30:00.123456Z'));
});

test('transfer serialization preserves recording microseconds while an absent reversal and optional FX timestamp remain absent', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $transfer = TransactionTransfer::factory()->create(['team_id' => $team->id, 'recorded_at' => '2026-10-01T12:00:00.123456+05:30']);

    $this->actingAs($owner)->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('transfer.recorded_at', '2026-10-01T06:30:00.123456Z')
            ->where('transfer.reversal', null)->where('transfer.execution_fx', null));
});
