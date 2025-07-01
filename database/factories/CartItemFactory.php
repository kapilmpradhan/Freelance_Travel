<?php

namespace Database\Factories;

use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition()
    {
        return [
            'id' => $this->faker->numberBetween(1, 1000),
            'user_id' => $this->faker->uuid(),
            'tdms_product_id' => $this->faker->numberBetween(1, 1000),
            'group_id' => $this->faker->uuid(),
            'product_version' => $this->faker->numberBetween(1, 10),
            'product_price_details_id' => $this->faker->numberBetween(1, 1000),
            'time_id' => '141',
            'commences' => '09:00',
            'booking_date' => $this->faker->date,
            'start_date' => $this->faker->date,
            'days' => 15,
            'selected_index' => $this->faker->numberBetween(0, 10),
            'availability' => [],
            'availability_last_updated_at' => $this->faker->dateTime,
            'booking_details' => [],
            'booking_quantity' => $this->faker->numberBetween(1, 10),
            'booking_data' => [],
            'user_order_id' => $this->faker->numberBetween(1, 1000),
            'quote_id' => $this->faker->numberBetween(1, 1000),
            'is_direct_purchase' => $this->faker->boolean,
        ];
    }

    public static function withProvidedData(array $providedDatas)
    {
        $overAllData = [];
        foreach ($providedDatas as $providedData) {;
            $dummyData = CartItem::factory()->make($providedData);
            $overAllData[] = $dummyData;
        }

        return collect($overAllData);
    }
}
