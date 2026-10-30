<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateFinancialAccount;
use App\Actions\Finance\UpdateFinancialAccount;
use App\Enums\FinancialAccountType;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ArchiveFinancialAccountRequest;
use App\Http\Requests\Finance\SaveFinancialAccountRequest;
use App\Models\AccountBalance;
use App\Models\Currency;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAccountController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('viewAny', [FinancialAccount::class, $currentTeam]);
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(FinancialAccountType::class)],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);
        $status = $filters['status'] ?? 'active';
        $accounts = $currentTeam->financialAccounts()->with(['owners', 'institution'])
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();

        return Inertia::render('accounts/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'accounts' => $accounts->through(fn (FinancialAccount $account) => $this->accountData($account)),
            'types' => $this->types(),
            'filters' => ['type' => $filters['type'] ?? '', 'status' => $status],
            'canManage' => Gate::forUser($user)->allows('create', [FinancialAccount::class, $currentTeam]),
        ]);
    }

    public function create(Request $request, Team $currentTeam): Response
    {
        Gate::forUser($this->authenticatedUser->resolve($request))->authorize('create', [FinancialAccount::class, $currentTeam]);

        return $this->form($currentTeam);
    }

    public function store(SaveFinancialAccountRequest $request, Team $currentTeam, CreateFinancialAccount $createAccount): RedirectResponse
    {
        $account = $createAccount->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('accounts.show', ['current_team' => $currentTeam, 'financial_account' => $account]);
    }

    public function show(Request $request, Team $currentTeam, FinancialAccount $financialAccount): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('view', $financialAccount);
        Gate::forUser($user)->authorize('viewAny', [AccountBalance::class, $financialAccount]);

        $latestBalance = $financialAccount->balances()->notVoided()->newestFirst()->first();
        $balances = $financialAccount->balances()->with('voidedBy:id,name')->newestFirst()->paginate(25);

        return Inertia::render('accounts/show', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'account' => $this->accountData($financialAccount->load(['owners', 'institution'])),
            'canManage' => Gate::forUser($user)->allows('update', $financialAccount),
            'currentBalance' => $latestBalance === null ? null : $this->balanceData($latestBalance),
            'balances' => $balances->through(fn (AccountBalance $balance) => $this->balanceData($balance)),
            'canRecordBalance' => Gate::forUser($user)->allows('create', [AccountBalance::class, $financialAccount]),
            'canVoidBalances' => $latestBalance !== null && Gate::forUser($user)->allows('voidObservation', $latestBalance->setRelation('team', $currentTeam)),
            'today' => now()->toDateString(),
        ]);
    }

    /** @return array<string, mixed> */
    private function balanceData(AccountBalance $balance): array
    {
        return [
            'id' => $balance->id,
            'amount' => $balance->amount,
            'as_of_date' => $balance->as_of_date->toDateString(),
            'recorded_at' => $balance->recorded_at->toISOString(),
            'source' => $balance->source->value,
            'created_by' => $balance->created_by,
            'voided_at' => $balance->voided_at?->toISOString(),
            'voided_by' => $balance->voided_by,
            'voided_by_name' => $balance->voided_at === null ? null : $balance->voidedBy?->name,
        ];
    }

    public function edit(Request $request, Team $currentTeam, FinancialAccount $financialAccount): Response
    {
        Gate::forUser($this->authenticatedUser->resolve($request))->authorize('update', $financialAccount);

        return $this->form($currentTeam, $financialAccount);
    }

    public function update(SaveFinancialAccountRequest $request, Team $currentTeam, FinancialAccount $financialAccount, UpdateFinancialAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle($this->authenticatedUser->resolve($request), $currentTeam, $financialAccount, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('accounts.show', ['current_team' => $currentTeam, 'financial_account' => $financialAccount]);
    }

    public function archive(ArchiveFinancialAccountRequest $request, Team $currentTeam, FinancialAccount $financialAccount): RedirectResponse
    {
        $user = $this->authenticatedUser->resolve($request);
        DB::transaction(function () use ($currentTeam, $financialAccount, $user): void {
            $team = Team::whereKey($currentTeam->id)->lockForUpdate()->firstOrFail();
            $account = $team->financialAccounts()->whereKey($financialAccount->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('archive', $account);
            $account->update(['archived_at' => $account->archived_at ?? now()]);
        }, attempts: 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account archived. History and holders have been preserved.')]);

        return to_route('accounts.index', ['current_team' => $currentTeam]);
    }

    private function form(Team $team, ?FinancialAccount $account = null): Response
    {
        return Inertia::render('accounts/form', [
            'team' => $team->only(['id', 'name', 'slug']),
            'account' => $account === null ? null : $this->accountData($account->load(['owners', 'institution'])),
            'hasHistory' => $account?->hasFinancialHistory() ?? false,
            'types' => $this->types(),
            'currencies' => Currency::orderBy('code')->get(['code', 'name']),
            'institutions' => $team->financialInstitutions()
                ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $account?->financial_institution_id))
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'archived_at']),
            'members' => $team->members()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn ($member) => $member->only(['id', 'name'])),
        ]);
    }

    /** @return list<array{value: string, label: string, isLiability: bool}> */
    private function types(): array
    {
        return array_map(fn (FinancialAccountType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'isLiability' => $type->isLiability(),
        ], FinancialAccountType::cases());
    }

    /** @return array<string, mixed> */
    private function accountData(FinancialAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'typeLabel' => $account->type->label(),
            'isLiability' => $account->type->isLiability(),
            'currency_code' => $account->currency_code,
            'financial_institution_id' => $account->financial_institution_id,
            'institution' => $account->institution?->only(['id', 'name']),
            'archived_at' => $account->archived_at?->toISOString(),
            'owners' => $account->owners->map(fn (FinancialAccountOwner $owner) => $owner->only(['id', 'owner_name', 'user_id']))->all(),
        ];
    }
}
