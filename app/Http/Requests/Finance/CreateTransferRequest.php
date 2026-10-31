<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\CreateTransfer;
use App\Models\Team;
use App\Models\TransactionTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [TransactionTransfer::class, $this->route('current_team')]);
    }

    protected function prepareForValidation(): void
    {
        $fx = $this->input('execution_fx');
        if (is_array($fx)) {
            foreach (['base_currency_code', 'quote_currency_code'] as $field) {
                if (isset($fx[$field]) && is_string($fx[$field])) {
                    $fx[$field] = strtoupper($fx[$field]);
                }
            }
            $this->merge(['execution_fx' => $fx]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return app(CreateTransfer::class)->rules($team);
    }
}
