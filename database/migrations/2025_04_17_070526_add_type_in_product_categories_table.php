<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ProductCategoryService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('type')->default(false);
        });

        // Update existing records to set is_experience to true for specific categories
        $experiences = [
            'Tours',
            'Adventures',
            'Hiking',
            'Nature',
            'Indigenous Culture',
            'Adrenaline Sports',
            'Sport Related',
            'Water Sports',
            'Health & Wellness',
            'Ice / Snow Activity',
            'Food / Drink Related',
            'Flights',
            'Hire Options',
        ];

        $transport = [
            'Bus',
            'Limosine',
            'Train',
        ];

        DB::table('product_categories')
            ->whereIn('label', $experiences)
            ->update([
                'type' => 'Experience',
            ]);

        DB::table('product_categories')
            ->whereIn('label', $transport)
            ->update([
                'type' => 'Transport',
            ]);

        DB::table('product_categories')
            ->whereIn('label', ['Hostel', 'Hotel'])
            ->update([
                'type' => 'Accommodation',
            ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
