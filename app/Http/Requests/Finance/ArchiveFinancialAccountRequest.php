<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ArchiveFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('archive', $this->route('financial_account'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'team_id' => ['prohibited']];
    }
}
