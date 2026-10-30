<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\ClassifyTransaction;
use App\Enums\TransactionKind;
use App\Models\AccountTransaction;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClassifyTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('account_transaction');

        return $transaction instanceof AccountTransaction
            && $transaction->kind !== TransactionKind::Transfer
            && Gate::allows('update', $transaction);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);
        $transaction = $this->route('account_transaction');
        abort_unless($transaction instanceof AccountTransaction, 404);
        $rules = app(ClassifyTransaction::class)->rules($team, $transaction);
        $rules['kind'][] = Rule::notIn([TransactionKind::Transfer->value]);

        return $rules;
    }
}
