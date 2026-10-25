<?php

namespace App\Concerns;

use App\Rules\BalanceAmount;

trait AccountBalanceValidationRules
{
    /** @return array<string, array<mixed>> */
    protected function accountBalanceRules(): array
    {
        return [
            'amount' => ['bail', 'required', 'string', 'max:64', new BalanceAmount],
            'as_of_date' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'team_id' => ['prohibited'],
            'financial_account_id' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'source' => ['prohibited'],
            'created_by' => ['prohibited'],
            'voided_at' => ['prohibited'],
            'voided_by' => ['prohibited'],
        ];
    }
}
