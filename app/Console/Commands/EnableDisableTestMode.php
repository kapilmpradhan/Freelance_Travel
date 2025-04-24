<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class EnableDisableTestMode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mode:test {--enable : Enable test mode}
                                    {--disable : Disable test mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable or disable test mode';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Read .env file
        $envFile = base_path('.env');
        if (!file_exists($envFile)) {
            $this->error('.env file not found!');
            return;
        }

        // Check if the command is run with --enable or --disable
        if ($this->option('enable')) {
            $key = 'TEST_MODE';
            $value = 'true';
        } elseif ($this->option('disable')) {
            $key = 'TEST_MODE';
            $value = 'false';
        } else {
            $this->error('Please specify --enbale or --disable.');
            return;
        }


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

        $message = $this->option('enable') ? 'enabled' : 'disabled';
        $this->info("Test mode {$message}");

        // Clear and cache the config
        Artisan::call('config:clear');
        Artisan::call('config:cache');

        $this->info('Configuration cache updated.');
    }
}
