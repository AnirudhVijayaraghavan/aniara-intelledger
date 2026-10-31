<?php

declare(strict_types=1);

namespace App\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Cross-currency multiplication/division rounds once to eight decimals using half-even.
 * Identity conversion preserves the original decimal string verbatim.
 */
readonly class FxQuote implements JsonSerializable
{
    public const int AMOUNT_SCALE = 8;

    public function __construct(
        public string $fromCurrencyCode,
        public string $toCurrencyCode,
        public string $valuationAt,
        public ?int $rateId,
        public string $baseCurrencyCode,
        public string $quoteCurrencyCode,
        public string $rate,
        public string $source,
        public ?string $effectiveAt,
        public ?string $recordedAt,
        public bool $isInverse,
    ) {
        if (! BigDecimal::of($rate)->isPositive()) {
            throw new InvalidArgumentException('Exchange rates must be positive.');
        }
    }

    public function apply(mixed $amount): string
    {
        if (! is_string($amount) || ! preg_match('/\\A-?[0-9]+(?:\\.[0-9]+)?\\z/', $amount)) {
            throw new InvalidArgumentException('Conversion amounts must be plain decimal strings.');
        }

        if ($this->fromCurrencyCode === $this->toCurrencyCode) {
            return $amount;
        }

        $value = BigDecimal::of($amount);

        return (string) ($this->isInverse
            ? $value->dividedBy($this->rate, self::AMOUNT_SCALE, RoundingMode::HalfEven)
            : $value->multipliedBy($this->rate)->toScale(self::AMOUNT_SCALE, RoundingMode::HalfEven));
    }

    /**
     * @return array{fromCurrencyCode: string, toCurrencyCode: string, valuationAt: string, rateId: int|null, baseCurrencyCode: string, quoteCurrencyCode: string, rate: string, source: string, effectiveAt: string|null, recordedAt: string|null, isInverse: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'fromCurrencyCode' => $this->fromCurrencyCode,
            'toCurrencyCode' => $this->toCurrencyCode,
            'valuationAt' => $this->valuationAt,
            'rateId' => $this->rateId,
            'baseCurrencyCode' => $this->baseCurrencyCode,
            'quoteCurrencyCode' => $this->quoteCurrencyCode,
            'rate' => $this->rate,
            'source' => $this->source,
            'effectiveAt' => $this->effectiveAt,
            'recordedAt' => $this->recordedAt,
            'isInverse' => $this->isInverse,
        ];
    }
}
