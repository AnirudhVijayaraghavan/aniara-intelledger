<?php

namespace App\Http\Requests\Finance;

use App\Concerns\FinancialAccountValidationRules;
use App\Models\FinancialAccount;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveFinancialAccountRequest extends FormRequest
{
    use FinancialAccountValidationRules;

    public function authorize(): bool
    {
        $account = $this->account();

        return $account instanceof FinancialAccount
            ? Gate::allows('update', $account)
            : Gate::allows('create', [FinancialAccount::class, $this->team()]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return $this->financialAccountRules($this->team(), $this->account());
    }

    public function withValidator(Validator $validator): void
    {
        $this->configureFinancialAccountValidator($validator, $this->team(), $this->account());
    }

    private function team(): Team
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return $team;
    }

    private function account(): ?FinancialAccount
    {
        $account = $this->route('financial_account');
        abort_if($account !== null && ! $account instanceof FinancialAccount, 404);

        return $account;
    }
}
