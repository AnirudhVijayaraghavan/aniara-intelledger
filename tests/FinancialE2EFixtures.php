<?php

declare(strict_types=1);

use App\Actions\Finance\CreateFinancialAccount;
use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\RecordAccountBalance;
use App\Enums\TeamRole;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use App\Models\TransactionRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\DisposablePostgreSql;
use Tests\Support\FinancialE2EEnvironment as E2E;

require dirname(__DIR__).'/vendor/autoload.php';

$boot = null;
$result = null;
$exit = 0;
try {
    E2E::assertInvocation(getenv(), worker: true);
    $connection = DisposablePostgreSql::configuration(DisposablePostgreSql::environment());
    $context = E2E::context(getenv('INTELLEDGER_E2E_CONTEXT') ?: '');
    $input = json_decode(stream_get_contents(STDIN, 1025), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($input) || ! in_array($input['operation'] ?? '', ['seed', 'inspect'], true)
        || ! is_string($input['case'] ?? null) || preg_match('/\A[a-f0-9]{16}\z/', $input['case']) !== 1) {
        throw new RuntimeException('Bounded synthetic fixture identity required.');
    }
    $boot = E2E::boot($connection, $context['run'], $context['key'], $context['receipt']);
    $boot['lease']->assertOwned($boot['app']);
    $case = $input['case'];
    $email = $case.'@example.test';
    $password = hash('sha256', $context['key'].$case);
    if ($input['operation'] === 'seed') {
        $result = DB::transaction(static function () use ($case, $email, $password): array {
            $actor = User::factory()->create(['name' => 'Synthetic Browser Owner', 'email' => $email, 'password' => $password]);
            $team = $actor->personalTeam();
            $team->update(['name' => 'Browser Household '.$case]);
            $other = User::factory()->create(['name' => 'Synthetic Other Owner', 'email' => 'other-'.$email]);
            $secondary = $other->personalTeam();
            $secondary->update(['name' => 'Browser Second Household '.$case]);
            $secondary->members()->attach($actor, ['role' => TeamRole::Member->value]);
            $hiddenOwner = User::factory()->create(['name' => 'Hidden Household Sentinel', 'email' => 'hidden-'.$email]);
            $hidden = $hiddenOwner->personalTeam();
            $make = static fn (User $owner, Team $household, string $name, string $currency): FinancialAccount => app(CreateFinancialAccount::class)->handle($owner, $household, [
                'name' => $name, 'type' => 'checking', 'currency_code' => $currency,
                'institution' => ['name' => 'Synthetic Financial Institution'],
                'owners' => [['owner_name' => $owner->name, 'user_id' => $owner->id]],
            ]);
            $account = $make($actor, $team, 'Browser Checking', 'USD');
            $secondAccount = $make($other, $secondary, 'Browser INR', 'INR');
            $hiddenAccount = $make($hiddenOwner, $hidden, 'Hidden Account Sentinel', 'USD');
            app(RecordAccountBalance::class)->handle($actor, $team, $account, ['amount' => '1234.12345678', 'as_of_date' => '2020-01-02']);
            app(CreateTransaction::class)->handle($actor, $team, $account, ['amount' => '25.12345678', 'kind' => 'income',
                'status' => 'posted', 'transaction_date' => '2020-01-01', 'posted_date' => '2020-01-02', 'description' => 'Synthetic recorded income']);
            $route = static fn (string $name, Team $household, array $parameters = []): string => route($name, ['current_team' => $household, ...$parameters], false);

            return ['email' => $email, 'password' => $password, 'teamName' => $team->name, 'secondTeamName' => $secondary->name,
                'dashboard' => $route('dashboard', $team), 'accounts' => $route('accounts.index', $team),
                'account' => $route('accounts.show', $team, ['financial_account' => $account]),
                'createTransaction' => $route('transactions.create', $team), 'transactions' => $route('transactions.index', $team),
                'upload' => $route('accounts.imports.store', $team, ['financial_account' => $account]),
                'secondAccounts' => $route('accounts.index', $secondary),
                'secondCreate' => $route('accounts.create', $secondary),
                'crossAccount' => $route('accounts.show', $team, ['financial_account' => $hiddenAccount]),
                'hiddenAccount' => $route('accounts.show', $hidden, ['financial_account' => $hiddenAccount]),
                'secondAccountName' => $secondAccount->name];
        });
    } else {
        $actor = User::where('email', $email)->sole();
        $team = $actor->personalTeam();
        $account = $team->financialAccounts()->where('name', 'Browser Checking')->sole();
        $batch = TransactionImportBatch::where('team_id', $team->id)->first();
        $row = $batch?->rows()->first();
        $result = ['transactions' => AccountTransaction::where('team_id', $team->id)->count(),
            'revisions' => TransactionRevision::where('team_id', $team->id)->count(),
            'balances' => $account->balances()->count(), 'amount' => $account->balances()->sole()->amount,
            'batches' => TransactionImportBatch::where('team_id', $team->id)->count(),
            'rows' => $batch?->rows()->count() ?? 0, 'committedRows' => $batch?->imported_rows ?? 0,
            'encryptedStaging' => $row !== null && $row->original_fields === ['headers' => ['Date', 'Amount', 'Description'],
                'values' => ['2020-01-02', '-12.00000001', 'Synthetic CSV row']]
                && ! str_contains($row->getRawOriginal('original_fields'), 'Synthetic CSV row')];
    }
} catch (Throwable) {
    fwrite(STDERR, "Financial E2E fixture refused or failed; no payload logged.\n");
    $exit = 1;
} finally {
    if ($boot !== null) {
        try {
            E2E::closeWorker($boot);
        } catch (Throwable) {
            fwrite(STDERR, "Financial E2E fixture cleanup failed; no payload logged.\n");
            $exit = 1;
        }
    }
}
if ($exit === 0 && is_array($result)) {
    echo json_encode($result, JSON_THROW_ON_ERROR);
}
exit($exit);
