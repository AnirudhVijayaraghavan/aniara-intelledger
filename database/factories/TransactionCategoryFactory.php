<?php

namespace Database\Factories;

use App\Enums\TransactionCategoryKind;
use App\Models\Team;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionCategory> */
class TransactionCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->word(),
            'kind' => TransactionCategoryKind::Expense,
            'parent_id' => null,
            'archived_at' => null,
        ];
    }
}
