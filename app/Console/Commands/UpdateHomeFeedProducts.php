<?php

namespace App\Console\Commands;

use App\Jobs\HomeFeedProductByCategories;
use App\Logging\Logger;
use Illuminate\Console\Command;

class UpdateHomeFeedProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:home
                            {--update : Update home feed products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updating home feed products';

    /**
     * Usage example:
     *
     * php artisan product:home --update
     */

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('update')) {
            $this->info('Updating home feed products');
            HomeFeedProductByCategories::dispatch();
        } else {
            $this->error('Please specify --update.');
        }

        return 0;
    }
}
