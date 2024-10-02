<?php

namespace Tests\Feature\Context;

use Dotenv\Dotenv;
use Behat\Behat\Context\Context;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Console\Kernel;

class BehatHooks implements Context
{
    private $databaseFile;

    public function __construct()
    {
        // Bootstrap the Laravel application
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        // Create a temporary SQLite database file
        $this->databaseFile = __DIR__ . '/../../../' . 'database/' . env('DB_DATABASE');

        // Set the database configuration for testing
        Config::set('database.connections.sqlite.database', $this->databaseFile);
        Config::set('database.default', env('DB_CONNECTION'));
    }
}
