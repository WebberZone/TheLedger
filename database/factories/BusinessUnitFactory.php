<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUnit>
 */
class BusinessUnitFactory extends Factory
{
    /** @var class-string<BusinessUnit> */
    protected $model = BusinessUnit::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_active' => true,
        ];
    }
}
