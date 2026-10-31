<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RecordExchangeRate;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class ExchangeRateController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        Gate::forUser($user)->authorize('viewAny', [ExchangeRate::class, $currentTeam]);
        foreach (['base_currency_code', 'quote_currency_code'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $request->merge([$field => strtoupper($value)]);
            }
        }
        $input = $request->validate([
            'base_currency_code' => ['nullable', 'string', Rule::exists('currencies', 'code')],
            'quote_currency_code' => ['nullable', 'string', Rule::exists('currencies', 'code')],
        ]);
        $currencies = Currency::orderBy('code')->get(['code', 'name']);
        $quote = $input['quote_currency_code'] ?? null;
        $preferredBase = $currencies->firstWhere('code', 'USD')?->code;
        $base = $input['base_currency_code'] ?? ($preferredBase !== $quote ? $preferredBase : null)
            ?? $currencies->first(fn (Currency $currency): bool => $currency->code !== $quote)?->code;
        $quote ??= $currencies->first(fn (Currency $currency): bool => $currency->code !== $base)?->code;

        return Inertia::render('exchange-rates/index', [
            'team' => $currentTeam->only(['id', 'name', 'slug']),
            'currencies' => $currencies,
            'defaults' => ['base_currency_code' => $base, 'quote_currency_code' => $quote],
            'canRecord' => Gate::forUser($user)->allows('create', [ExchangeRate::class, $currentTeam]),
            'nowUtc' => now()->utc()->toISOString(),
            'rates' => $currentTeam->exchangeRates()->orderByDesc('effective_at')->orderByDesc('recorded_at')->orderByDesc('id')
                ->paginate(25)->through(fn (ExchangeRate $rate): array => $this->rateData($rate)),
        ]);
    }

    public function store(RecordExchangeRateRequest $request, Team $currentTeam, RecordExchangeRate $recordRate): RedirectResponse
    {
        $recordRate->handle($this->authenticatedUser->resolve($request), $currentTeam, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Manual FX rate recorded. Historical observations have been preserved.')]);

        return to_route('exchange-rates.index', ['current_team' => $currentTeam]);
    }

    /** @return array<string, int|string> */
    private function rateData(ExchangeRate $rate): array
    {
        $effectiveAt = $rate->effective_at?->toISOString();
        $recordedAt = $rate->recorded_at?->toISOString();
        if ($effectiveAt === null || $recordedAt === null) {
            throw new UnexpectedValueException('Stored exchange-rate timestamps must be valid.');
        }

        return [
            'id' => $rate->id, 'baseCurrencyCode' => $rate->base_currency_code,
            'quoteCurrencyCode' => $rate->quote_currency_code, 'rate' => $rate->rate,
            'effectiveAt' => $effectiveAt, 'recordedAt' => $recordedAt, 'source' => $rate->source,
        ];
    }
}
