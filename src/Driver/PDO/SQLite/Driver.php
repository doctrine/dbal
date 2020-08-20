<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;

use function array_merge;

final class Driver extends AbstractSQLiteDriver
{
    /** @var mixed[] */
    private $userDefinedFunctions = [
        'sqrt' => ['callback' => [SqlitePlatform::class, 'udfSqrt'], 'numArgs' => 1],
        'mod'  => ['callback' => [SqlitePlatform::class, 'udfMod'], 'numArgs' => 2],
        'locate'  => ['callback' => [SqlitePlatform::class, 'udfLocate'], 'numArgs' => -1],
    ];

    /**
     * {@inheritdoc}
     */
    public function connect(array $params): ConnectionInterface
    {
        $driverOptions = $params['driverOptions'] ?? [];

        if (isset($driverOptions['userDefinedFunctions'])) {
            $this->userDefinedFunctions = array_merge(
                $this->userDefinedFunctions,
                $driverOptions['userDefinedFunctions']
            );
            unset($driverOptions['userDefinedFunctions']);
        }

        $connection = new Connection(
            $this->constructPdoDsn($params),
            $params['user'] ?? '',
            $params['password'] ?? '',
            $driverOptions
        );

        $pdo = $connection->getWrappedConnection();

        foreach ($this->userDefinedFunctions as $fn => $data) {
            $pdo->sqliteCreateFunction($fn, $data['callback'], $data['numArgs']);
        }

        return $connection;
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }

        return $dsn;
    }
}
