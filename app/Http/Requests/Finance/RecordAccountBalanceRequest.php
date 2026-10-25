<?php

namespace App\Http\Requests\Finance;

use App\Concerns\AccountBalanceValidationRules;
use App\Models\AccountBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecordAccountBalanceRequest extends FormRequest
{
    use AccountBalanceValidationRules;

    public function authorize(): bool
    {
        return Gate::allows('create', [AccountBalance::class, $this->route('financial_account')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return $this->accountBalanceRules();
    }
}
