<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUpdatedUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('users'); // Drop accounts table if exists for fresh start
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('uuid')->primary(); // Use UUID as the primary key
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 100)->unique();
            $table->string('password')->nullable();
            $table->boolean('is_email_verified')->default(false); // Set default to false
            $table->enum('sso_type', ['email', 'google', 'apple']);
            $table->timestamps(); // Default created_at and updated_at fields
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users'); // Drop accounts table when rolling back
    }
}
