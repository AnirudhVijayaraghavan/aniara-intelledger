<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\ReverseTransfer;
use App\Models\TransactionTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReverseTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transfer = $this->route('transaction_transfer');

        return $transfer instanceof TransactionTransfer && Gate::allows('reverse', $transfer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return app(ReverseTransfer::class)->rules();
    }
}
