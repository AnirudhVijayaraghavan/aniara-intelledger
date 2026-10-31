<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\SavePayee;
use App\Models\Payee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePayeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('payee');

        return $record instanceof Payee
            ? Gate::allows('update', $record)
            : Gate::allows('create', [Payee::class, $this->route('current_team')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return SavePayee::rules();
    }
}
