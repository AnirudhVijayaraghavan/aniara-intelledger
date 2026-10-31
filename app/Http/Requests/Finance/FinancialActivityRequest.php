<?php

namespace App\Http\Requests\Finance;

use App\Models\AccountTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinancialActivityRequest extends FormRequest
{
    /** @var list<array{months: int, startDate: string, endDate: string}>|null */
    private ?array $monthPresets = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [AccountTransaction::class, $this->route('current_team')]);
    }

    /** @return list<array{months: int, startDate: string, endDate: string}> */
    public function presets(): array
    {
        if ($this->monthPresets === null) {
            $month = CarbonImmutable::now()->startOfMonth();
            $this->monthPresets = array_map(fn (int $count): array => [
                'months' => $count,
                'startDate' => $month->subMonths($count - 1)->toDateString(),
                'endDate' => $month->endOfMonth()->toDateString(),
            ], [1, 3, 6, 12]);
        }

        return $this->monthPresets;
    }

    protected function prepareForValidation(): void
    {
        $default = $this->presets()[3];
        $this->mergeIfMissing(['start_date' => $default['startDate'], 'end_date' => $default['endDate']]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'currency' => ['sometimes', 'required', 'string', Rule::in(['USD', 'INR'])],
            'team_id' => ['prohibited'],
        ];
    }
}
