<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tools\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use PackageVersions\Versions;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Handles running the Console Tools inside Symfony Console context.
 */
class ConsoleRunner
{
    /**
     * Create a Symfony Console HelperSet
     */
    public static function createHelperSet(Connection $connection) : HelperSet
    {
        return new HelperSet([
            'db' => new ConnectionHelper($connection),
        ]);
    }

    /**
     * Runs console with the given helperset.
     *
     * @param array<int, Command> $commands
     */
    public static function run(HelperSet $helperSet, array $commands = []) : void
    {
        $cli = new Application('Doctrine Command Line Interface', Versions::getVersion('doctrine/dbal'));

        $cli->setCatchExceptions(true);
        $cli->setHelperSet($helperSet);

        self::addCommands($cli);

        $cli->addCommands($commands);
        $cli->run();
    }

    public static function addCommands(Application $cli) : void
    {
        $cli->addCommands([
            new RunSqlCommand(),
            new ReservedWordsCommand(),
        ]);
    }

    /**
     * Prints the instructions to create a configuration file
     */
    public static function printCliConfigTemplate() : void
    {
        echo <<<'HELP'
You are missing a "cli-config.php" or "config/cli-config.php" file in your
project, which is required to get the Doctrine-DBAL Console working. You can use the
following sample as a template:

<?php
use Doctrine\DBAL\Tools\Console\ConsoleRunner;

// replace with the mechanism to retrieve DBAL connection in your app
$connection = getDBALConnection();

// You can append new commands to $commands array, if needed

return ConsoleRunner::createHelperSet($connection);

HELP;
    }
}
