<?php

namespace App\Http\Requests\Finance;

use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadTransactionImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $account = $this->route('financial_account');

        return $team instanceof Team && $account instanceof FinancialAccount && $account->team_id === $team->id
            && Gate::allows('create', [TransactionImportBatch::class, $account]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'max:10240', 'extensions:csv', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
            'team_id' => ['prohibited'], 'financial_account_id' => ['prohibited'],
            'mapping' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }
}
