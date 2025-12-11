<?php

namespace Database\Factories;

use App\Models\CartCustomerDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartCustomerDetailFactory extends Factory
{
    protected $model = CartCustomerDetail::class;

    public function definition()
    {
        return [
            'user_id' => $this->faker->uuid(),
            'id' => $this->faker->numberBetween(1, 1000),
            'title' => $this->faker->randomElement(['Mr', 'Mrs']),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'date_of_birth' => $this->faker->date(),
            'email' => $this->faker->unique()->safeEmail,
            'postal_code' => $this->faker->postcode,
            'customer_index' => $this->faker->randomNumber(),
            'country_code' => '020',
            'phone_number' => $this->faker->phoneNumber,
            'quote_id' => null,
            'user_order_id' => null,
            'is_direct_purchase' => $this->faker->boolean,
            'is_deleted' => false,
            'is_primary' => $this->faker->boolean,
        ];
    }
}
