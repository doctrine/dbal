<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\API\SQLite\UserDefinedFunctions;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use PDO;
use PDOException;

use function array_merge;

final class Driver extends AbstractSQLiteDriver
{
    /** @var mixed[] */
    private array $userDefinedFunctions = [
        'sqrt' => [
            'callback' => 'sqrt',
            'numArgs' => 1,
        ],
        'mod' => [
            'callback' => [UserDefinedFunctions::class, 'mod'],
            'numArgs' => 2,
        ],
        'locate' => [
            'callback' => [UserDefinedFunctions::class, 'locate'],
            'numArgs' => -1,
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function connect(array $params): Connection
    {
        $driverOptions = $params['driverOptions'] ?? [];

        if (isset($driverOptions['userDefinedFunctions'])) {
            $this->userDefinedFunctions = array_merge(
                $this->userDefinedFunctions,
                $driverOptions['userDefinedFunctions']
            );
            unset($driverOptions['userDefinedFunctions']);
        }

        try {
            $pdo = new PDO(
                $this->constructPdoDsn($params),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $driverOptions
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        foreach ($this->userDefinedFunctions as $fn => $data) {
            $pdo->sqliteCreateFunction($fn, $data['callback'], $data['numArgs']);
        }

        return new Connection($pdo);
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param mixed[] $params
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
