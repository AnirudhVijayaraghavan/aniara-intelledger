<?php

namespace Database\Factories;

use App\Models\Payee;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payee> */
class PayeeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company(),
            'archived_at' => null,
        ];
    }
}
