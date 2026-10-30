<?php

use App\Enums\TeamRole;
use App\Enums\TransactionImportBatchStatus;
use App\Enums\TransactionImportRowStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\TransactionImportBatch;
use App\Models\TransactionImportRow;
use App\Models\TransactionRevision;
use App\Models\TransactionSourceReference;
use App\Models\User;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

test('staged source fields are encrypted hidden and retain exact decimal strings without financial writes', function () {
    $batch = TransactionImportBatch::factory()->create(['mapping' => ['amount' => 'Amount']]);
    $original = ['Date' => '01/10/2026', 'Amount' => '9999999999999999.12345678', 'Description' => str_repeat('Private narrative भारत ', 30)];
    $row = TransactionImportRow::factory()->create([
        'transaction_import_batch_id' => $batch->id, 'original_fields' => $original,
        'normalized_data' => ['amount' => '9999999999999999.12345678', 'currency_code' => 'USD', 'description' => 'Private narrative'],
        'validation_errors' => ['date' => ['Review source date']], 'validation_warnings' => ['Ambiguous date'],
        'duplicate_candidates' => [], 'decision_reason' => 'Private review',
    ])->fresh();

    expect($row->original_fields)->toBe($original);
    expect($row->normalized_data['amount'])->toBe('9999999999999999.12345678');
    expect(DB::table('transaction_import_rows')->where('id', $row->id)->value('original_fields'))->not->toContain('Private narrative', '9999999999999999');
    expect(DB::table('transaction_import_rows')->where('id', $row->id)->value('normalized_data'))->not->toContain('9999999999999999');
    expect(json_encode($row->normalized_data, JSON_THROW_ON_ERROR))->toContain('"amount":"9999999999999999.12345678"');
    expect($row->toArray())->not->toHaveKeys(['original_fields', 'normalized_data', 'validation_errors', 'validation_warnings', 'duplicate_candidates', 'decision_reason']);
    expect($batch->fresh()->toArray())->not->toHaveKeys(['file_disk', 'file_path', 'original_filename', 'file_sha256', 'mapping', 'mapping_history', 'preview_digest']);
    expect($row->load('batch')->toJson())->not->toContain('Private narrative', 'transaction-imports/', 'statement.csv');
    expect($batch->account->transactionImportBatches()->sole()->id)->toBe($batch->id);
    expect($batch->team->transactionImportRows()->sole()->id)->toBe($row->id);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
    $this->assertDatabaseCount('account_balances', 0);
});

test('normalized amounts never accept float integer malformed excess scale or overflow', function (mixed $amount, string $exception) {
    expect(fn () => TransactionImportRow::factory()->create(['normalized_data' => ['amount' => $amount]]))->toThrow($exception);
    $this->assertDatabaseCount('transaction_import_rows', 0);
})->with([
    'fractional float' => [1.5, InvalidArgumentException::class],
    'whole float before JSON encoding' => [1.0, InvalidArgumentException::class],
    'integer' => [100, InvalidArgumentException::class],
    'malformed' => ['not-money', NumberFormatException::class],
    'excess scale' => ['0.123456789', RoundingNecessaryException::class],
    'overflow' => ['10000000000000000', InvalidArgumentException::class],
]);

test('normalization preserves negative adjustments and pads decimal strings without rounding', function () {
    $row = TransactionImportRow::factory()->create(['normalized_data' => ['amount' => '-0.1', 'currency_code' => 'USD']]);
    expect($row->fresh()->normalized_data['amount'])->toBe('-0.10000000');
    expect(fn () => TransactionImportRow::factory()->create(['normalized_data' => ['currency_code' => 'INR']]))->toThrow(DomainException::class);
    expect(fn () => TransactionImportRow::factory()->make(['normalized_data' => ['foreign' => ['amount' => 1.0]]]))->toThrow(InvalidArgumentException::class);
});

test('one account file identity survives reprocessing while other accounts may import the same file', function () {
    $batch = TransactionImportBatch::factory()->create();
    expect(fn () => TransactionImportBatch::factory()->create(['financial_account_id' => $batch->financial_account_id, 'file_sha256' => $batch->file_sha256]))->toThrow(QueryException::class);
    $other = TransactionImportBatch::factory()->create(['file_sha256' => $batch->file_sha256]);
    expect($other->id)->not->toBe($batch->id);
});

test('candidate fingerprints coexist but a file record cannot be staged twice', function () {
    $batch = TransactionImportBatch::factory()->create();
    $fingerprint = hash('sha256', 'two identical legitimate purchases');
    TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id, 'record_number' => 1, 'candidate_fingerprint' => $fingerprint]);
    TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id, 'record_number' => 2, 'candidate_fingerprint' => $fingerprint]);
    expect($batch->rows()->count())->toBe(2);
    expect($batch->rows()->pluck('record_number')->all())->toBe([1, 2]);
    expect(fn () => TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id, 'record_number' => 1]))->toThrow(QueryException::class);
    TransactionImportRow::factory()->create(['record_number' => 1, 'candidate_fingerprint' => $fingerprint]);
    $this->assertDatabaseCount('transaction_import_rows', 3);
});

test('source identity is unique across transactions and identity versions within its account and namespace', function () {
    $first = AccountTransaction::factory()->posted()->create();
    $second = AccountTransaction::factory()->posted()->create(['financial_account_id' => $first->financial_account_id]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $first->id]);
    expect(fn () => TransactionSourceReference::factory()->create([
        'account_transaction_id' => $second->id, 'source_key_hash' => $reference->source_key_hash, 'identity_version' => 2,
    ]))->toThrow(QueryException::class);
    TransactionSourceReference::factory()->create(['account_transaction_id' => $second->id, 'source_key_hash' => $reference->source_key_hash, 'source_namespace' => 'manual_csv']);
    TransactionSourceReference::factory()->create(['source_key_hash' => $reference->source_key_hash]);
    expect($first->sourceReferences()->sole()->id)->toBe($reference->id);
    expect($first->account->transactionSourceReferences()->count())->toBe(2);
    expect($reference->fresh()->toArray())->not->toHaveKey('source_key_hash');
});

test('composite foreign keys reject cross team and same team cross account references', function (string $case) {
    $account = FinancialAccount::factory()->create();
    $other = FinancialAccount::factory()->create(['team_id' => $account->team_id]);
    $foreign = FinancialAccount::factory()->create();
    $batch = TransactionImportBatch::factory()->create(['financial_account_id' => $account->id]);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $row = TransactionImportRow::factory()->imported($transaction)->create(['transaction_import_batch_id' => $batch->id]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'transaction_import_row_id' => $row->id]);
    $otherTransaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $other->id]);
    $foreignTransaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $foreign->id]);
    $sameAccountTransaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $table = match ($case) {
        'batch foreign account' => 'transaction_import_batches',
        'row foreign team', 'row wrong account', 'outcome wrong account', 'outcome foreign team', 'missing revision' => 'transaction_import_rows',
        default => 'transaction_source_references',
    };
    $id = match ($table) {
        'transaction_import_batches' => $batch->id,
        'transaction_import_rows' => $row->id,
        default => $reference->id,
    };
    $changes = match ($case) {
        'batch foreign account' => ['financial_account_id' => $foreign->id],
        'row foreign team' => ['team_id' => $foreign->team_id],
        'row wrong account' => ['financial_account_id' => $other->id],
        'outcome wrong account', 'source wrong account' => ['account_transaction_id' => $otherTransaction->id],
        'outcome foreign team', 'source foreign team' => ['account_transaction_id' => $foreignTransaction->id],
        'missing revision' => ['applied_revision_number' => 99],
        'source row wrong outcome' => ['account_transaction_id' => $sameAccountTransaction->id],
    };
    expect(fn () => DB::transaction(fn () => DB::table($table)->where('id', $id)->update($changes)))->toThrow(QueryException::class);
})->with(['batch foreign account', 'row foreign team', 'row wrong account', 'outcome wrong account', 'outcome foreign team', 'missing revision', 'source wrong account', 'source foreign team', 'source row wrong outcome']);

test('outcome and approval checks reject incomplete persistence states', function (string $case) {
    $row = TransactionImportRow::factory()->create();
    $table = in_array($case, ['negative count', 'missing approval', 'stale approval'], true) ? 'transaction_import_batches' : 'transaction_import_rows';
    $id = $table === 'transaction_import_batches' ? $row->transaction_import_batch_id : $row->id;
    $changes = match ($case) {
        'negative count' => ['processed_rows' => -1],
        'missing approval' => ['status' => 'approved'],
        'stale approval' => ['approved_at' => now(), 'approved_mapping_version' => 2, 'preview_digest' => hash('sha256', 'x'), 'approved_preview_digest' => hash('sha256', 'x')],
        'missing outcome' => ['status' => 'imported'],
        'missing transaction for revision' => ['applied_revision_number' => 1],
        'invalid row number' => ['record_number' => 0],
    };
    expect(fn () => DB::transaction(fn () => DB::table($table)->where('id', $id)->update($changes)))->toThrow(QueryException::class);
})->with(['negative count', 'missing approval', 'stale approval', 'missing outcome', 'missing transaction for revision', 'invalid row number']);

test('duplicate candidate references must belong to the selected household account', function () {
    $batch = TransactionImportBatch::factory()->create();
    $same = AccountTransaction::factory()->create(['financial_account_id' => $batch->financial_account_id]);
    $foreign = AccountTransaction::factory()->create();
    $row = TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id, 'duplicate_candidates' => [$same->id]]);
    expect($row->fresh()->duplicate_candidates)->toBe([$same->id]);
    expect(fn () => $row->update(['duplicate_candidates' => [$foreign->id]]))->toThrow(DomainException::class);
    $otherAccount = FinancialAccount::factory()->create(['team_id' => $batch->team_id]);
    $sameTeamOtherAccount = AccountTransaction::factory()->create(['financial_account_id' => $otherAccount->id]);
    expect(fn () => $row->refresh()->update(['duplicate_candidates' => [$sameTeamOtherAccount->id]]))->toThrow(DomainException::class);
});

test('duplicate candidate lists reject ambiguous JSON identities before encryption', function (array $candidates) {
    expect(fn () => TransactionImportRow::factory()->make(['duplicate_candidates' => $candidates]))->toThrow(DomainException::class);
})->with([
    'string IDs' => [['1']],
    'float IDs' => [[1.0]],
    'negative IDs' => [[-1]],
    'associative candidates' => [['id' => 1]],
]);

test('original file and row identities cannot be overwritten through quiet saves', function (string $field) {
    $row = TransactionImportRow::factory()->create();
    $record = $field === 'original_fields' ? $row : $row->batch;
    $change = match ($field) {
        'original_fields' => ['Description' => 'Rewritten source'],
        'file_sha256' => hash('sha256', 'replacement file'),
        'mapping' => ['amount' => 'Other'],
    };
    $before = $record->fresh()->getAttributes();
    $record->setAttribute($field, $change);
    expect(fn () => $record->saveQuietly())->toThrow(DomainException::class);
    expect($record->fresh()->getAttributes())->toBe($before);
})->with(['original_fields', 'file_sha256', 'mapping']);

test('financial import policies allow household history but restrict management and raw source access', function (?TeamRole $role, bool $read, bool $manage) {
    $batch = TransactionImportBatch::factory()->create();
    $row = TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id]);
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $batch->financial_account_id]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id]);
    $actor = User::factory()->create();
    if ($role !== null) {
        $batch->team->members()->attach($actor, ['role' => $role->value]);
    }
    $gate = Gate::forUser($actor);
    foreach ([$batch, $row, $reference] as $record) {
        expect($gate->allows('view', $record))->toBe($read);
        expect($gate->allows('viewAny', [$record::class, $batch->team]))->toBe($read);
        expect($gate->allows('delete', $record))->toBeFalse();
    }
    expect($gate->allows('create', [TransactionImportBatch::class, $batch->account]))->toBe($manage);
    expect($gate->allows('update', $batch))->toBe($manage);
    expect($gate->allows('reprocess', $batch))->toBe($manage);
    expect($gate->allows('viewSourceData', $batch))->toBe($manage);
    expect($gate->allows('viewSourceData', $row))->toBe($manage);
    expect($gate->allows('create', [TransactionImportRow::class, $batch]))->toBe($manage);
    expect($gate->allows('update', $row))->toBe($manage);
    expect($gate->allows('create', [TransactionSourceReference::class, $batch->account]))->toBe($manage);
    expect($gate->allows('update', $reference))->toBeFalse();
})->with([[TeamRole::Owner, true, true], [TeamRole::Admin, true, true], [TeamRole::Member, true, false], [null, false, false]]);

test('explicit team scopes isolate import history regardless of current team preference', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $batch = TransactionImportBatch::factory()->create(['financial_account_id' => $account->id]);
    $row = TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id]);
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id]);
    $foreignBatch = TransactionImportBatch::factory()->create();
    $foreign = $foreignBatch->team;
    $foreign->members()->attach($actor, ['role' => 'owner']);
    $actor->switchTeam($foreign);
    expect(TransactionImportBatch::forTeam($team)->pluck('id')->all())->toBe([$batch->id]);
    expect(TransactionImportRow::forTeam($team)->pluck('id')->all())->toBe([$row->id]);
    expect(TransactionSourceReference::forTeam($team)->pluck('id')->all())->toBe([$reference->id]);
});

test('authorized remapping versions the same file clears approval and preserves committed rows and identities', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $batch = TransactionImportBatch::factory()->approved()->create(['financial_account_id' => $account->id, 'mapping' => ['amount' => 'Debit'], 'approved_by' => $actor->id]);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $committed = TransactionImportRow::factory()->imported($transaction)->create(['transaction_import_batch_id' => $batch->id]);
    $uncommitted = TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'transaction_import_row_id' => $committed->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $committed->fresh()->getAttributes();
    $balanceBefore = $balance->fresh()->getAttributes();
    $transactions = AccountTransaction::get()->toArray();
    $audits = TransactionRevision::get()->toArray();
    $revised = $batch->reviseMapping($actor, ['amount' => 'Credit'], 1, 'Correct source sign mapping');

    expect($revised->id)->toBe($batch->id);
    expect($revised->mapping_version)->toBe(2);
    expect($revised->status)->toBe(TransactionImportBatchStatus::Mapping);
    expect($revised->approved_at)->toBeNull();
    expect($revised->approved_by)->toBeNull();
    expect($revised->approved_preview_digest)->toBeNull();
    expect($revised->preview_digest)->toBeNull();
    expect($revised->mapping_history[0]['mapping'])->toBe(['amount' => 'Debit']);
    expect($revised->mapping_history[0]['approved_by'])->toBe($actor->id);
    expect($committed->fresh()->getAttributes())->toBe($before);
    expect($reference->fresh()->account_transaction_id)->toBe($transaction->id);
    expect(fn () => $uncommitted->update(['status' => TransactionImportRowStatus::Valid]))->toThrow(DomainException::class);
    $uncommitted->refresh()->update(['mapping_version' => 2, 'normalized_data' => ['amount' => '-100'], 'status' => TransactionImportRowStatus::Valid]);
    expect($uncommitted->fresh()->mapping_version)->toBe(2);
    expect(fn () => $batch->reviseMapping($actor, ['amount' => 'Other'], 1, 'Stale request'))->toThrow(DomainException::class);
    expect(AccountTransaction::get()->toArray())->toBe($transactions);
    expect(TransactionRevision::get()->toArray())->toBe($audits);
    expect($balance->fresh()->getAttributes())->toBe($balanceBefore);
    $this->assertDatabaseCount('transaction_import_batches', 1);
});

test('remapping cannot bypass authorization or ordinary save protection', function () {
    $batch = TransactionImportBatch::factory()->create();
    $actor = User::factory()->create();
    $batch->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    expect(fn () => $batch->reviseMapping($actor, ['amount' => 'Amount'], 1, 'Changed map'))->toThrow(AuthorizationException::class);
    $batch->mapping_version = 2;
    expect(fn () => $batch->saveQuietly())->toThrow(DomainException::class);
    expect($batch->fresh()->mapping_version)->toBe(1);
});

test('archived accounts and processing or purged batches reject remapping', function (string $condition) {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $batch = TransactionImportBatch::factory()->approved()->create(['financial_account_id' => $account->id]);
    if ($condition === 'archived') {
        $account->update(['archived_at' => now()]);
    } elseif ($condition === 'processing') {
        $batch->update(['status' => TransactionImportBatchStatus::Processing]);
    } else {
        $batch->update(['file_path' => null, 'file_purged_at' => now()]);
    }
    expect(Gate::forUser($actor)->allows('view', $batch->fresh()))->toBeTrue();
    expect(fn () => $batch->reviseMapping($actor, [], 1, 'Correct mapping'))->toThrow(AuthorizationException::class);
})->with(['archived', 'processing', 'purged']);

test('committed provenance cannot be rewritten quietly deleted or detached by database cascades', function () {
    $transaction = AccountTransaction::factory()->posted()->create();
    $row = TransactionImportRow::factory()->imported($transaction)->create();
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'transaction_import_row_id' => $row->id]);
    expect(fn () => $row->updateQuietly(['status' => TransactionImportRowStatus::Pending]))->toThrow(DomainException::class);
    $reference->source_key_hash = hash('sha256', 'changed');
    expect(fn () => $reference->saveQuietly())->toThrow(DomainException::class);
    foreach ([$row->batch, $row, $reference] as $record) {
        expect(fn () => $record->delete())->toThrow(DomainException::class);
    }
    foreach (['financial_accounts' => $transaction->financial_account_id, 'account_transactions' => $transaction->id,
        'transaction_import_batches' => $row->transaction_import_batch_id, 'transaction_import_rows' => $row->id] as $table => $id) {
        expect(fn () => DB::transaction(fn () => DB::table($table)->where('id', $id)->delete()))->toThrow(QueryException::class);
    }
    expect($reference->fresh()->importRow->id)->toBe($row->id);
    expect($transaction->importRows()->sole()->id)->toBe($row->id);
});

test('deleting source actors retains batches approvals row outcomes and references', function () {
    $actor = User::factory()->create();
    $batch = TransactionImportBatch::factory()->approved()->create(['uploaded_by' => $actor->id, 'approved_by' => $actor->id]);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $batch->financial_account_id]);
    $row = TransactionImportRow::factory()->imported($transaction)->create(['transaction_import_batch_id' => $batch->id, 'decided_by' => $actor->id]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'transaction_import_row_id' => $row->id, 'recorded_by' => $actor->id]);
    $actor->delete();
    expect($batch->fresh()->uploaded_by)->toBeNull();
    expect($batch->fresh()->approved_by)->toBeNull();
    expect($batch->fresh()->approved_at)->not->toBeNull();
    expect($row->fresh()->decided_by)->toBeNull();
    expect($reference->fresh()->recorded_by)->toBeNull();
    expect($row->fresh()->account_transaction_id)->toBe($transaction->id);
    expect($reference->fresh()->transaction->id)->toBe($transaction->id);
});
