<?php

namespace Tests\Feature\Context;

use Dotenv\Dotenv;
use Behat\Behat\Context\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Console\Kernel;

class BehatHooks implements Context
{
    private $databaseFile;

    public function __construct()
    {
        // Load the .env.testing file
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../', '.env.testing');
        $dotenv->load();

        // Bootstrap the Laravel application
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        // Create a temporary SQLite database file
        $this->databaseFile = __DIR__ . '/../../../' . 'database/database.testing.sqlite';

        // Set the database configuration for testing
        Config::set('database.connections.sqlite.database', $this->databaseFile);
        Config::set('database.default', env('DB_CONNECTION'));
    }

    // public function __destruct()
    // {
    //     // Remove the database tables after the tests complete
    //     $tables = DB::select('SELECT name FROM sqlite_master WHERE type="table"');
    //     DB::statement('PRAGMA foreign_keys = OFF');
    //     foreach ($tables as $table) {
    //         if ($table->name !== 'sqlite_sequence') {
    //             DB::statement('DROP TABLE IF EXISTS ' . $table->name);
    //         }
    //     }
    //     DB::statement('PRAGMA foreign_keys = ON');
    // }
}
