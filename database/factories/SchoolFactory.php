<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' School';
        return ['name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999), 'primary_color' => '#1D4ED8', 'secondary_color' => '#0F766E', 'is_active' => true];
    }
}
