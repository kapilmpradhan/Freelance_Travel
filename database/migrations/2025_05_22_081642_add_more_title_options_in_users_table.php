<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `title` ENUM('Master', 'Mr', 'Miss', 'Mrs', 'Ms', 'Mx') NULL");
        DB::statement("ALTER TABLE `cart_customer_details` MODIFY `title` ENUM('Master', 'Mr', 'Miss', 'Mrs', 'Ms', 'Mx') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `title` ENUM('Mr', 'Mrs') NULL");
        DB::statement("ALTER TABLE `cart_customer_details` MODIFY `title` ENUM('Mr', 'Mrs') NULL");
    }
};
