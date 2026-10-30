<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\ReverseTransaction;
use App\Models\AccountTransaction;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReverseTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('account_transaction');

        return $transaction instanceof AccountTransaction && Gate::allows('reverse', $transaction);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $transaction = $this->route('account_transaction');
        abort_unless($team instanceof Team && $transaction instanceof AccountTransaction, 404);

        return app(ReverseTransaction::class)->rules($team, $transaction);
    }
}
