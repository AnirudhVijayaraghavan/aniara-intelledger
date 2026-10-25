<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Currency> */
class CurrencyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('X??')),
            'name' => 'Test Currency',
            'minor_units' => 2,
        ];
    }
}
