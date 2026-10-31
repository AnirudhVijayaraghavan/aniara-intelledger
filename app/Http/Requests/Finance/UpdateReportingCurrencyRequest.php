<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\UpdateTeamReportingCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateReportingCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateReportingCurrency', $this->route('team'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return UpdateTeamReportingCurrency::rules();
    }
}
