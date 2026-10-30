<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\ReverseTransfer;
use App\Enums\TransactionStatus;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateTransferRequest;
use App\Http\Requests\Finance\ListTransfersRequest;
use App\Http\Requests\Finance\ReverseTransferRequest;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class TransferController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function index(ListTransfersRequest $request, Team $currentTeam): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('viewAny', [TransactionTransfer::class, $currentTeam]);
        $filters = $request->safe()->except('page');

        return Inertia::render('transfers/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'transfers' => $currentTeam->transactionTransfers()->whereNull('reverses_transfer_id')->with($this->relations())
                ->when($filters['financial_account_id'] ?? null, fn ($query, $accountId) => $query
                    ->where(fn ($query) => $query->where('source_account_id', $accountId)->orWhere('destination_account_id', $accountId)))
                ->orderByDesc('recorded_at')->orderByDesc('id')->paginate(25)->withQueryString()
                ->through(fn (TransactionTransfer $transfer): array => $this->transferData($transfer)),
            'accounts' => $this->accounts($currentTeam),
            'filters' => $filters,
            'canCreate' => Gate::forUser($user)->allows('create', [TransactionTransfer::class, $currentTeam]),
        ]);
    }

    public function create(Request $request, Team $currentTeam): Response
    {
        Gate::forUser($this->authenticatedUser->resolve($request))->authorize('create', [TransactionTransfer::class, $currentTeam]);

        return Inertia::render('transfers/create', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'accounts' => $this->accounts($currentTeam, true),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(CreateTransferRequest $request, Team $currentTeam, CreateTransfer $createTransfer): RedirectResponse
    {
        $transfer = $createTransfer->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer recorded. Balance observations have not changed.']);

        return to_route('transfers.show', ['current_team' => $currentTeam, 'transaction_transfer' => $transfer]);
    }

    public function show(Request $request, Team $currentTeam, TransactionTransfer $transactionTransfer, ReverseTransfer $reverseTransfer): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('view', $transactionTransfer);
        $transfer = $currentTeam->transactionTransfers()->whereKey($transactionTransfer->id)->with($this->relations())->firstOrFail();
        $transfer->setRelation('team', $currentTeam);
        $canReverse = Gate::forUser($user)->allows('reverse', $transfer)
            && $transfer->reverses_transfer_id === null && $transfer->reversal === null
            && $transfer->sourceAccount->archived_at === null && $transfer->destinationAccount->archived_at === null
            && $transfer->sourceTransaction->status === TransactionStatus::Posted && $transfer->destinationTransaction->status === TransactionStatus::Posted;

        return Inertia::render('transfers/show', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'transfer' => $this->transferData($transfer),
            'canReverse' => $canReverse,
            'reversalPreview' => $canReverse ? $reverseTransfer->preview($transfer) : null,
            'reversalReason' => $transfer->reverses_transfer_id === null ? null : $transfer->sourceTransaction->revisions()->where('revision_number', 1)->value('reason'),
            'today' => now()->toDateString(),
        ]);
    }

    public function reverse(ReverseTransferRequest $request, Team $currentTeam, TransactionTransfer $transactionTransfer, ReverseTransfer $reverseTransfer): RedirectResponse
    {
        $reversal = $reverseTransfer->handle($this->authenticatedUser->resolve($request), $currentTeam, $transactionTransfer, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer reversal recorded. Original transactions and balance observations are unchanged.']);

        return to_route('transfers.show', ['current_team' => $currentTeam, 'transaction_transfer' => $reversal]);
    }

    /** @return array<int|string, mixed> */
    private function relations(): array
    {
        return ['sourceAccount.institution', 'destinationAccount.institution',
            'sourceTransaction' => fn ($query) => $query->withMax('revisions', 'revision_number'),
            'destinationTransaction' => fn ($query) => $query->withMax('revisions', 'revision_number'),
            'creator:id,name', 'reversal:id,team_id,reverses_transfer_id,recorded_at', 'reversesTransfer'];
    }

    /** @return list<array<string, mixed>> */
    private function accounts(Team $team, bool $activeOnly = false): array
    {
        return array_values($team->financialAccounts()->with('institution')
            ->when($activeOnly, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('name')->orderBy('id')->get()->map(fn (FinancialAccount $account): array => $this->accountData($account))->all());
    }

    /** @return array<string, mixed> */
    private function accountData(FinancialAccount $account): array
    {
        return [
            'id' => $account->id, 'name' => $account->name, 'currency_code' => $account->currency_code,
            'isLiability' => $account->type->isLiability(), 'typeLabel' => $account->type->label(),
            'institution' => $account->institution?->only(['id', 'name']),
            'archived_at' => $account->archived_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function legData(AccountTransaction $leg, FinancialAccount $account): array
    {
        return [
            'id' => $leg->id, 'amount' => $leg->amount, 'currency_code' => $leg->currency_code,
            'transaction_date' => $leg->transaction_date->toDateString(), 'posted_date' => $leg->posted_date?->toDateString(),
            'status' => $leg->status->value, 'description' => $leg->description,
            'excluded_from_analytics' => $leg->excluded_from_analytics, 'account' => $this->accountData($account),
            'expected_revision' => (int) $leg->getAttribute('revisions_max_revision_number'),
        ];
    }

    /** @return array<string, mixed> */
    private function transferData(TransactionTransfer $transfer): array
    {
        $reversal = $transfer->reversal;
        $recordedAt = $transfer->recorded_at?->toISOString();
        $reversalRecordedAt = $reversal?->recorded_at?->toISOString();
        if ($recordedAt === null || ($reversal !== null && $reversalRecordedAt === null)) {
            throw new UnexpectedValueException('Transfer recording timestamps must be valid.');
        }

        return [
            'id' => $transfer->id,
            'reverses_transfer_id' => $transfer->reverses_transfer_id,
            'reversal' => $reversal === null ? null : [
                'id' => $reversal->id, 'recorded_at' => $reversalRecordedAt,
            ],
            'source' => $this->legData($transfer->sourceTransaction, $transfer->sourceAccount),
            'destination' => $this->legData($transfer->destinationTransaction, $transfer->destinationAccount),
            'recorded_at' => $recordedAt,
            'creator' => $transfer->creator?->only(['id', 'name']),
            'execution_fx' => $this->executionFxData($transfer),
            'original_execution_fx' => $transfer->reversesTransfer === null ? null : $this->executionFxData($transfer->reversesTransfer),
        ];
    }

    /** @return array<string, mixed>|null */
    private function executionFxData(TransactionTransfer $transfer): ?array
    {
        return $transfer->fx_rate === null ? null : [
            'base_currency_code' => $transfer->fx_base_currency_code, 'quote_currency_code' => $transfer->fx_quote_currency_code,
            'rate' => $transfer->fx_rate, 'source' => $transfer->fx_source, 'effective_at' => $transfer->fx_effective_at?->toISOString(),
        ];
    }
}
