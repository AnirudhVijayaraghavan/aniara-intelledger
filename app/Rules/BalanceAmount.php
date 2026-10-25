<?php

namespace App\Rules;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class BalanceAmount implements ValidationRule
{
    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/\A-?[0-9]+(?:\.[0-9]{1,8})?\z/', $value)) {
            $fail('Enter a decimal amount with up to 8 decimal places, without commas or exponents.');

            return;
        }

        $amount = BigDecimal::of($value)->toScale(8, RoundingMode::Unnecessary);

        if ($amount->abs()->isGreaterThan('9999999999999999.99999999')) {
            $fail('The amount must be between -9999999999999999.99999999 and 9999999999999999.99999999.');
        }
    }
}
