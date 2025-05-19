<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetDiscount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cart:discount
                            {--percentage= : Set discount percentage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set cart discount';

    /**
     * Usage example:
     *
     * php artisan cart:discount --percentage=25
     */

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $cartDiscount = $this->option('percentage');
        if (!$cartDiscount) {
            $this->error('Please specify percentage using --percentage=<percentage>');
            return;
        }

        // Read .env file
        $envFile = base_path('.env');
        if (!file_exists($envFile)) {
            $this->error('.env file not found!');
            return;
        }

        $key = 'DISCOUNT_PERCENTAGE';
        $value = (int) $cartDiscount;

        // Update or add the key-value pair
        $envContent = file_get_contents($envFile);
        $pattern = "/^{$key}=.*$/m";
        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
        } else {
            $envContent .= PHP_EOL . "{$key}={$value}";
        }

        // Write back to the .env file
        file_put_contents($envFile, $envContent);

        // Clear and cache the config
        Artisan::call('config:clear');
        Artisan::call('config:cache');

        $this->info("Cart discount set to $value%.");
    }
}
