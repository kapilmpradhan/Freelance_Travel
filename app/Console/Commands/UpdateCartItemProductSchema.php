<?php

namespace App\Console\Commands;

use App\Jobs\UpdateProductSchemaJob;
use Illuminate\Console\Command;

class UpdateCartItemProductSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:cart
                            {--update-schema : Update cart item products schema}
                            {--schema-version= : The version to be used for the update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updating cart item products schema';

    /**
     * Usage example:
     *
     * php artisan product:cart --update-schema --version=<version>
     */

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('update-schema')) {
            $schemaVersion = $this->option('schema-version'); // Get the version option

            if (!$schemaVersion) {
                $this->error('Please specify a version using --schema-version=<version>');
                return;
            }
            UpdateProductSchemaJob::dispatch($schemaVersion);
        } else {
            $this->error('Please specify --update-schema');
        }
    }
}
