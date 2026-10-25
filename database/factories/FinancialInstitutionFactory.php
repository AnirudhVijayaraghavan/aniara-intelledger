<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialInstitution> */
class FinancialInstitutionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company(),
            'country_code' => 'US',
            'archived_at' => null,
        ];
    }
}
