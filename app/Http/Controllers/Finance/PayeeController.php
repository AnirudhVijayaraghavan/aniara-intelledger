<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\ArchivePayee;
use App\Actions\Finance\SavePayee;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ArchivePayeeRequest;
use App\Http\Requests\Finance\ListPayeesRequest;
use App\Http\Requests\Finance\SavePayeeRequest;
use App\Models\Payee;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayeeController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function index(ListPayeesRequest $request, Team $currentTeam): Response
    {
        $filters = array_replace(['search' => '', 'status' => 'active'], $request->safe()->except('page'));
        $status = $filters['status'] ?: 'active';
        $query = $currentTeam->payees()->withCount('transactions')
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'));
        if ($filters['search'] !== null && $filters['search'] !== '') {
            $pattern = '%'.$filters['search'].'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereLike('name', $pattern);
            });
        }

        return Inertia::render('payees/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'records' => $query->orderBy('name')->orderBy('id')->paginate(25)->withQueryString()
                ->through(fn (Payee $record): array => $this->data($record)),
            'filters' => $filters,
            'canManage' => Gate::allows('create', [Payee::class, $currentTeam]),
        ]);
    }

    public function store(SavePayeeRequest $request, Team $currentTeam, SavePayee $save): JsonResponse|RedirectResponse
    {
        $record = $save->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['payee' => $this->data($record->loadCount('transactions'))], 201);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payee created.']);

        return to_route('payees.index', ['current_team' => $currentTeam]);
    }

    public function update(SavePayeeRequest $request, Team $currentTeam, Payee $payee, SavePayee $save): RedirectResponse
    {
        $save->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated(), $payee);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payee updated.']);

        return to_route('payees.index', ['current_team' => $currentTeam]);
    }

    public function archive(ArchivePayeeRequest $request, Team $currentTeam, Payee $payee, ArchivePayee $archive): RedirectResponse
    {
        $archive->handle($this->authenticatedUser->resolve($request), $currentTeam, $payee);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payee archived. Historical references have been preserved.']);

        return to_route('payees.index', ['current_team' => $currentTeam]);
    }

    /** @return array<string, mixed> */
    private function data(Payee $record): array
    {
        return [
            ...$record->only(['id', 'name', 'archived_at']),
            'transactions_count' => (int) $record->getAttribute('transactions_count'),
        ];
    }
}
