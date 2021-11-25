<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('access_tokens')->insert([
            'token' => 'rRraPA72vHpJDTzZwH09lZ4CcF38Jg'
        ]);

    }
}
