<?php

namespace Framework\Core\Bootstrap;

use Dotenv\Dotenv;
use Framework\Support\Env;
use Framework\Contracts\Core\Application;
use Dotenv\Exception\InvalidFileException;

class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Framework\Contracts\Core\Application|\Framework\Core\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        if ($app->configurationIsCached()) {
            return;
        }

        $this->checkForSpecificEnvironmentFile($app);

        try {
            $this->createDotenv($app)->safeLoad();
        } catch (InvalidFileException $e) {
            $this->writeErrorAndDie($e);
        }
    }

    /**
     * Detect if a custom environment file matching the APP_ENV exists.
     *
     * @param  \Framework\Contracts\Core\Application|\Framework\Core\Application  $app
     * @return void
     */
    protected function checkForSpecificEnvironmentFile($app)
    {
        $environment = Env::get('APP_ENV');

        if (! $environment) {
            return;
        }

        $this->setEnvironmentFilePath(
            $app,
            $app->environmentFile() . '.' . $environment
        );
    }

    /**
     * Load a custom environment file.
     *
     * @param  \Framework\Contracts\Core\Application|\Framework\Core\Application  $app
     * @param  string  $file
     * @return bool
     */
    protected function setEnvironmentFilePath($app, $file)
    {
        if (is_file($app->environmentPath() . '/' . $file)) {
            $app->loadEnvironmentFrom($file);

            return true;
        }

        return false;
    }

    /**
     * Create a Dotenv instance.
     *
     * @param  \Framework\Contracts\Core\Application|\Framework\Core\Application  $app
     * @return \Dotenv\Dotenv
     */
    protected function createDotenv($app)
    {
        return Dotenv::create(
            Env::getRepository(),
            $app->environmentPath(),
            $app->environmentFile()
        );
    }

    /**
     * Write the error information to the screen and exit.
     *
     * @param  \Dotenv\Exception\InvalidFileException  $e
     * @return never
     */
    protected function writeErrorAndDie(InvalidFileException $e)
    {
        // $output = (new ConsoleOutput)->getErrorOutput();

        // $output->writeln('The environment file is invalid!');
        // $output->writeln($e->getMessage());

        echo 'The environment file is invalid!' . "\n";
        echo $e->getMessage() . "\n";

        http_response_code(500);

        exit(1);
    }
}
