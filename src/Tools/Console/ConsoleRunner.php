<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tools\Console;

use Composer\InstalledVersions;
use Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

use function assert;

/**
 * Handles running the Console Tools inside Symfony Console context.
 */
class ConsoleRunner
{
    /**
     * Runs console with the given connection provider.
     *
     * @param array<int, Command> $commands
     *
     * @throws Exception
     */
    public static function run(ConnectionProvider $connectionProvider, array $commands = []): void
    {
        $version = InstalledVersions::getVersion('doctrine/dbal');
        assert($version !== null);

        $cli = new Application('Doctrine Command Line Interface', $version);

        $cli->setCatchExceptions(true);
        self::addCommands($cli, $connectionProvider);
        $cli->addCommands($commands);
        $cli->run();
    }

    public static function addCommands(Application $cli, ConnectionProvider $connectionProvider): void
    {
        $cli->addCommands([
            new RunSqlCommand($connectionProvider),
            new ReservedWordsCommand($connectionProvider),
        ]);
    }
}
