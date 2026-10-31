<?php

namespace App\Http\Requests\Finance;

use App\Models\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ArchiveTransactionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('transaction_category');

        return $record instanceof TransactionCategory && Gate::allows('archive', $record);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['team_id' => ['prohibited'], 'archived_at' => ['prohibited']];
    }
}
