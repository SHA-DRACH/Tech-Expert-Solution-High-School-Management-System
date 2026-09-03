<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Admission> */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'application_number' => 'ADM-'.now()->year.'-'.fake()->unique()->numberBetween(10000, 99999),
            'status' => 'submitted',
            'student_first_name' => fake()->firstName(),
            'student_last_name' => fake()->lastName(),
            'intended_class' => 'Grade '.fake()->numberBetween(7, 12),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->phoneNumber(),
        ];
    }
}
