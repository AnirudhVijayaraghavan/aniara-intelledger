<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\CreateTransaction;
use App\Enums\TransactionKind;
use App\Models\AccountTransaction;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createAny', [AccountTransaction::class, $this->route('current_team')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['status' => $this->input('status', 'pending')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);
        $rules = app(CreateTransaction::class)->rules($team);
        $rules['financial_account_id'] = ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('team_id', $team->id)->whereNull('archived_at')];
        $rules['kind'][] = Rule::notIn([TransactionKind::Transfer->value]);

        return $rules;
    }
}
