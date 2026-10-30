<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\RecordExchangeRate;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecordExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [ExchangeRate::class, $this->route('current_team')]);
    }

    protected function prepareForValidation(): void
    {
        foreach (['base_currency_code', 'quote_currency_code'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => strtoupper($value)]);
            }
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return RecordExchangeRate::rules();
    }
}
