<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CancelTransaction;
use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\PostTransaction;
use App\Actions\Finance\ReverseTransaction;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelTransactionRequest;
use App\Http\Requests\Finance\ClassifyTransactionRequest;
use App\Http\Requests\Finance\CreateTransactionRequest;
use App\Http\Requests\Finance\ListTransactionsRequest;
use App\Http\Requests\Finance\PostTransactionRequest;
use App\Http\Requests\Finance\ReverseTransactionRequest;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class TransactionController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function index(ListTransactionsRequest $request, Team $currentTeam): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('viewAny', [AccountTransaction::class, $currentTeam]);
        $filters = $request->safe()->except('page');
        $transactions = $currentTeam->accountTransactions()->with(['account', 'category', 'payee'])
            ->withMax('revisions', 'revision_number');
        foreach (['financial_account_id', 'status', 'kind', 'category_id'] as $field) {
            if (isset($filters[$field])) {
                $transactions->where($field, $filters[$field]);
            }
        }
        if (isset($filters['date_from'])) {
            $transactions->where('transaction_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $transactions->where('transaction_date', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->toDateString());
        }

        return Inertia::render('transactions/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'transactions' => $transactions->orderByDesc('transaction_date')->orderByDesc('recorded_at')->orderByDesc('id')
                ->paginate(25)->withQueryString()->through(fn (AccountTransaction $transaction): array => $this->transactionData($transaction)),
            'filters' => $filters,
            'accounts' => $this->accountOptions($currentTeam),
            'categories' => $this->categoryOptions($currentTeam),
            'canCreate' => Gate::forUser($user)->allows('createAny', [AccountTransaction::class, $currentTeam]),
            'canCreateTransfer' => Gate::forUser($user)->allows('create', [TransactionTransfer::class, $currentTeam]),
        ]);
    }

    public function create(Request $request, Team $currentTeam): Response
    {
        Gate::forUser($this->authenticatedUser->resolve($request))->authorize('createAny', [AccountTransaction::class, $currentTeam]);

        return Inertia::render('transactions/create', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'accounts' => $this->accountOptions($currentTeam, true),
            ...$this->classificationOptions($currentTeam),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(CreateTransactionRequest $request, Team $currentTeam, CreateTransaction $create): RedirectResponse
    {
        $user = $this->authenticatedUser->resolve($request);
        $account = $currentTeam->financialAccounts()->whereKey($request->validated('financial_account_id'))->firstOrFail();
        $transaction = $create->handle($user, $currentTeam, $account, $request->safe()->except('financial_account_id'));

        return $this->redirect($currentTeam, $transaction, 'Transaction recorded.');
    }

    public function show(Request $request, Team $currentTeam, AccountTransaction $accountTransaction): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('view', $accountTransaction);
        $accountTransaction = $currentTeam->accountTransactions()->whereKey($accountTransaction->id)
            ->with(['account', 'category', 'payee', 'reversal:id,reverses_transaction_id'])
            ->withExists('replacement')->withMax('revisions', 'revision_number')->firstOrFail();
        $accountTransaction->setRelation('team', $currentTeam);
        $accountTransaction->account->setRelation('team', $currentTeam);
        $canManage = Gate::forUser($user)->allows('update', $accountTransaction)
            && $accountTransaction->kind !== TransactionKind::Transfer
            && $accountTransaction->status !== TransactionStatus::Cancelled;

        return Inertia::render('transactions/show', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'transaction' => $this->transactionData($accountTransaction),
            'revisions' => $accountTransaction->revisions()->reorder()->with('actor:id,name')->orderByDesc('revision_number')
                ->paginate(25, ['*'], 'revisions_page')->withQueryString()->through(fn (TransactionRevision $revision): array => $this->revisionData($revision)),
            'canManage' => $canManage,
            'canReverse' => Gate::forUser($user)->allows('reverse', $accountTransaction)
                && $accountTransaction->status === TransactionStatus::Posted
                && $accountTransaction->reverses_transaction_id === null && $accountTransaction->replaces_transaction_id === null
                && $accountTransaction->reversal === null && ! $accountTransaction->getAttribute('replacement_exists'),
            'reversalId' => $accountTransaction->reversal?->id,
            ...($canManage ? $this->classificationOptions($currentTeam, $accountTransaction) : ['categories' => [], 'payees' => []]),
            'today' => now()->toDateString(),
        ]);
    }

    public function post(PostTransactionRequest $request, Team $currentTeam, AccountTransaction $accountTransaction, PostTransaction $post): RedirectResponse
    {
        $transaction = $post->handle($this->authenticatedUser->resolve($request), $currentTeam, $accountTransaction, $request->validated());

        return $this->redirect($currentTeam, $transaction, 'Transaction posted.');
    }

    public function cancel(CancelTransactionRequest $request, Team $currentTeam, AccountTransaction $accountTransaction, CancelTransaction $cancel): RedirectResponse
    {
        $transaction = $cancel->handle($this->authenticatedUser->resolve($request), $currentTeam, $accountTransaction, $request->validated());

        return $this->redirect($currentTeam, $transaction, 'Pending transaction cancelled.');
    }

    public function classify(ClassifyTransactionRequest $request, Team $currentTeam, AccountTransaction $accountTransaction, ClassifyTransaction $classify): RedirectResponse
    {
        $transaction = $classify->handle($this->authenticatedUser->resolve($request), $currentTeam, $accountTransaction, $request->validated());

        return $this->redirect($currentTeam, $transaction, 'Classification saved.');
    }

    public function reverse(ReverseTransactionRequest $request, Team $currentTeam, AccountTransaction $accountTransaction, ReverseTransaction $reverse): RedirectResponse
    {
        $transaction = $reverse->handle($this->authenticatedUser->resolve($request), $currentTeam, $accountTransaction, $request->validated());

        return $this->redirect($currentTeam, $transaction, 'Reversal recorded. The original transaction and balance observations are unchanged.');
    }

    /** @return array<string, mixed> */
    private function revisionData(TransactionRevision $revision): array
    {
        $recordedAt = $revision->recorded_at?->toISOString();
        if ($recordedAt === null) {
            throw new UnexpectedValueException('Transaction revision recording timestamp must be valid.');
        }

        return [
            'id' => $revision->id, 'revision_number' => $revision->revision_number,
            'operation' => $revision->operation->value, 'snapshot' => $revision->snapshot,
            'actor' => $revision->actor?->only(['id', 'name']), 'reason' => $revision->getAttribute('reason'),
            'recorded_at' => $recordedAt,
        ];
    }

    private function redirect(Team $team, AccountTransaction $transaction, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return to_route('transactions.show', ['current_team' => $team, 'account_transaction' => $transaction]);
    }

    /** @return array<string, mixed> */
    private function transactionData(AccountTransaction $transaction): array
    {
        return [
            ...$transaction->canonicalSnapshot(),
            'account' => $this->accountData($transaction->account),
            'category' => $transaction->category?->only(['id', 'name', 'kind']),
            'payee' => $transaction->payee?->only(['id', 'name']),
            'expected_revision' => (int) $transaction->getAttribute('revisions_max_revision_number'),
        ];
    }

    /** @return array<string, mixed> */
    private function accountData(FinancialAccount $account): array
    {
        return [
            'id' => $account->id, 'name' => $account->name, 'currency_code' => $account->currency_code,
            'typeLabel' => $account->type->label(), 'isLiability' => $account->type->isLiability(),
            'archived_at' => $account->archived_at?->toISOString(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function accountOptions(Team $team, bool $activeOnly = false): array
    {
        return array_values($team->financialAccounts()->when($activeOnly, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('name')->orderBy('id')->get()->map(fn (FinancialAccount $account): array => $this->accountData($account))->all());
    }

    /** @return list<array<string, mixed>> */
    private function categoryOptions(Team $team, ?int $retainedId = null, bool $activeOnly = false): array
    {
        return array_values($team->transactionCategories()
            ->when($activeOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $retainedId)))
            ->with('parent:id,name')->orderBy('name')->orderBy('id')->get()->map(fn ($category): array => [
                ...$category->only(['id', 'name', 'kind', 'parent_id', 'archived_at']),
                'parent' => $category->parent?->only(['id', 'name']),
            ])->all());
    }

    /** @return array<string, mixed> */
    private function classificationOptions(Team $team, ?AccountTransaction $transaction = null): array
    {
        return [
            'categories' => $this->categoryOptions($team, $transaction?->category_id, true),
            'payees' => $team->payees()->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $transaction?->payee_id))
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'archived_at']),
        ];
    }
}
