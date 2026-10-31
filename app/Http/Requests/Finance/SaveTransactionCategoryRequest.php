<?php

namespace App\Http\Requests\Finance;

use App\Concerns\TransactionCategoryValidationRules;
use App\Models\Team;
use App\Models\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveTransactionCategoryRequest extends FormRequest
{
    use TransactionCategoryValidationRules;

    public function authorize(): bool
    {
        $record = $this->route('transaction_category');

        return $record instanceof TransactionCategory
            ? Gate::allows('update', $record)
            : Gate::allows('create', [TransactionCategory::class, $this->route('current_team')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return $this->categoryRules($team, $this->route('transaction_category'));
    }

    public function withValidator(Validator $validator): void
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);
        $this->configureCategoryValidator($validator, $team, $this->route('transaction_category'));
    }
}
