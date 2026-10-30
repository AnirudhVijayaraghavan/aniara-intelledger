<?php

namespace App\Http\Requests\Finance;

use App\Models\Team;
use App\Models\TransactionTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListTransfersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [TransactionTransfer::class, $this->route('current_team')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return [
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')->where('team_id', $team->id)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
