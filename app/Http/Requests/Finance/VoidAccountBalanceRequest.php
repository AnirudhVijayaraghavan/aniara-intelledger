<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VoidAccountBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('voidObservation', $this->route('balance'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
            'team_id' => ['prohibited'],
            'financial_account_id' => ['prohibited'],
            'amount' => ['prohibited'],
            'as_of_date' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'source' => ['prohibited'],
            'created_by' => ['prohibited'],
            'voided_at' => ['prohibited'],
            'voided_by' => ['prohibited'],
        ];
    }
}
