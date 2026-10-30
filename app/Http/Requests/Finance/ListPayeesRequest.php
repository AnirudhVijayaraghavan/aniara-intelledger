<?php

namespace App\Http\Requests\Finance;

use App\Models\Payee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListPayeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [Payee::class, $this->route('current_team')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
