<?php

namespace App\Http\Requests\Finance;

use App\Models\Payee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ArchivePayeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('payee');

        return $record instanceof Payee && Gate::allows('archive', $record);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['team_id' => ['prohibited'], 'archived_at' => ['prohibited']];
    }
}
