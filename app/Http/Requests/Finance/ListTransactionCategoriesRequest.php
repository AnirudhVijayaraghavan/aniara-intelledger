<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransactionCategoryKind;
use App\Models\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListTransactionCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [TransactionCategory::class, $this->route('current_team')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'kind' => ['nullable', Rule::enum(TransactionCategoryKind::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
