<?php

declare(strict_types=1);

namespace App\Data;

use JsonSerializable;

readonly class FxConversion implements JsonSerializable
{
    public function __construct(
        public string $amount,
        public string $convertedAmount,
        public FxQuote $quote,
    ) {}

    /** @return array{amount: string, convertedAmount: string, quote: FxQuote} */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'convertedAmount' => $this->convertedAmount,
            'quote' => $this->quote,
        ];
    }
}
