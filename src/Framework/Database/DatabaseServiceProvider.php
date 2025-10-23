<?php

namespace Framework\Database;

use PDO;
use Framework\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('db.connection', function ($app) {
            // return $app['db']->connection();

            $database = "database";
            $databasePath = $app->databasePath("$database.sqlite");

            return new Connection(
                new PDO("sqlite:$databasePath"),
                $database,
                '',
                [
                    'driver' => 'sqlite',
                    'name' => 'sqlite',
                    'database' => $databasePath,
                ]
            );
        });
    }
}
