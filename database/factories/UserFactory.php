<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'uuid' => Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => fake()->randomElement(['agent', 'admin', 'client', 'owner']),
            'user_type' => 'employee',
            'city' => fake()->city(),
            'status' => 'active',
            'commission_rate' => fake()->randomFloat(2, 1, 5),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function agent()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'agent',
            'commission_rate' => fake()->randomFloat(2, 2, 4),
        ]);
    }

    public function client()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
            'user_type' => 'client',
        ]);
    }
}
