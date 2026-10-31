<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [AccountTransaction::class, $this->route('current_team')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return [
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')->where('team_id', $team->id)],
            'category_id' => ['nullable', 'integer', Rule::exists('transaction_categories', 'id')->where('team_id', $team->id)],
            'status' => ['nullable', Rule::enum(TransactionStatus::class)],
            'kind' => ['nullable', Rule::enum(TransactionKind::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
