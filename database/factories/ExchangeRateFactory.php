<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'base_currency_code' => 'USD',
            'quote_currency_code' => 'INR',
            'rate' => '83.000000000000000000',
            'effective_at' => now()->utc(),
            'recorded_at' => now()->utc(),
            'source' => 'manual',
            'created_by' => null,
        ];
    }
}
