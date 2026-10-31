<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\ArchiveTransactionCategory;
use App\Actions\Finance\SaveTransactionCategory;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ArchiveTransactionCategoryRequest;
use App\Http\Requests\Finance\ListTransactionCategoriesRequest;
use App\Http\Requests\Finance\SaveTransactionCategoryRequest;
use App\Models\Team;
use App\Models\TransactionCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCategoryController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function index(ListTransactionCategoriesRequest $request, Team $currentTeam): Response
    {
        $filters = array_replace(['search' => '', 'status' => 'active', 'kind' => ''], $request->safe()->except('page'));
        $status = $filters['status'] ?: 'active';
        $query = $currentTeam->transactionCategories()->withCount('transactions')->with('parent:id,name')->withExists('children')
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'));
        if ($filters['search'] !== null && $filters['search'] !== '') {
            $pattern = '%'.$filters['search'].'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereLike('name', $pattern)->orWhereHas('parent', fn ($parent) => $parent->whereLike('name', $pattern));
            });
        }
        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        return Inertia::render('categories/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'records' => $query->orderBy('name')->orderBy('id')->paginate(25)->withQueryString()
                ->through(fn (TransactionCategory $record): array => $this->data($record)),
            'filters' => $filters,
            'canManage' => Gate::allows('create', [TransactionCategory::class, $currentTeam]),
            'roots' => $currentTeam->transactionCategories()->whereNull('parent_id')->orderBy('name')->orderBy('id')->get(['id', 'name', 'kind', 'parent_id', 'archived_at']),
        ]);
    }

    public function store(SaveTransactionCategoryRequest $request, Team $currentTeam, SaveTransactionCategory $save): JsonResponse|RedirectResponse
    {
        $record = $save->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['category' => $this->data($record->loadCount('transactions')->load('parent:id,name')->loadExists('children'))], 201);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category created.']);

        return to_route('categories.index', ['current_team' => $currentTeam]);
    }

    public function update(SaveTransactionCategoryRequest $request, Team $currentTeam, TransactionCategory $transactionCategory, SaveTransactionCategory $save): RedirectResponse
    {
        $save->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated(), $transactionCategory);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category updated.']);

        return to_route('categories.index', ['current_team' => $currentTeam]);
    }

    public function archive(ArchiveTransactionCategoryRequest $request, Team $currentTeam, TransactionCategory $transactionCategory, ArchiveTransactionCategory $archive): RedirectResponse
    {
        $archive->handle($this->authenticatedUser->resolve($request), $currentTeam, $transactionCategory);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category archived. Historical references have been preserved.']);

        return to_route('categories.index', ['current_team' => $currentTeam]);
    }

    /** @return array<string, mixed> */
    private function data(TransactionCategory $record): array
    {
        return [
            ...$record->only(['id', 'name', 'archived_at', 'kind', 'parent_id']),
            'transactions_count' => (int) $record->getAttribute('transactions_count'),
            'parent' => $record->parent?->only(['id', 'name']),
            'has_children' => (bool) $record->getAttribute('children_exists'),
        ];
    }
}
