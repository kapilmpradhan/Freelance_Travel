<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'is_email_verified' => false,
            'sso_type' => 'email',
            'profile_status' => 'success',
            'is_ops' => false,
            'is_temporarily_deleted' => false,
            'is_permanently_deleted' => false,
            'is_points_displayed' => true,
        ];
    }

    /**
     * Indicate that the user's email is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_email_verified' => true,
        ]);
    }

    /**
     * Indicate that the user signed up via Google.
     */
    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'sso_type' => 'google',
            'is_email_verified' => true,
        ]);
    }

    /**
     * Indicate that the user signed up via Apple.
     */
    public function apple(): static
    {
        return $this->state(fn (array $attributes) => [
            'sso_type' => 'apple',
            'is_email_verified' => true,
        ]);
    }

    /**
     * Indicate that the user is an admin/ops user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ops' => true,
        ]);
    }

    /**
     * Indicate that the user is temporarily deleted.
     */
    public function temporarilyDeleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_temporarily_deleted' => true,
            'deletion_date' => now(),
        ]);
    }

    /**
     * Indicate that the user is permanently deleted.
     */
    public function permanentlyDeleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_permanently_deleted' => true,
        ]);
    }
}
