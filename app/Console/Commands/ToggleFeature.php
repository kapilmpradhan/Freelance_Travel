<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

class ToggleFeature extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feature:toggle 
                            {name? : The name of the feature} 
                            {--enable : Enable the feature} 
                            {--disable : Disable the feature}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable or disable a feature';

    /**
     * Usage example:
     *
     * Enable a feature:
     * php artisan feature:toggle <feature-name> --enable
     *
     * Disable a feature:
     * php artisan feature:toggle <feature-name> --disable
     */

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $featureName = $this->argument('name');
        // Handle missing feature name
        if (empty($featureName)) {
            $this->error(
                'Feature name is required.'
                . 'Please provide a valid feature name.'
                . '[php artisan feature:toggle <feature-name> --enable/--disable]'
            );
            return 1;
        }

        if ($this->option('enable')) {
            Feature::activate($featureName);
            $this->info("Feature '{$featureName}' has been enabled.");
        } elseif ($this->option('disable')) {
            Feature::deactivate($featureName);
            $this->info("Feature '{$featureName}' has been disabled.");
        } else {
            $this->error('Please specify either --enable or --disable option.');
        }

        return 0;
    }
}
