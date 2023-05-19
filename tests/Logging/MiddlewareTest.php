<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Logging;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class MiddlewareTest extends TestCase
{
    private Driver $driver;
    private TestLogger $logger;

    public function setUp(): void
    {
        $connection = $this->createMock(Connection::class);

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')
            ->willReturn($connection);

        $this->logger = new TestLogger();

        $middleware   = new Middleware($this->logger);
        $this->driver = $middleware->wrap($driver);
    }

    public function testConnectAndDisconnect(): void
    {
        $this->driver->connect([
            'user' => 'admin',
            'password' => 'Passw0rd!',
        ]);

        self::assertTrue($this->logger->hasInfo([
            'message' => 'Connecting with parameters {params}',
            'context' => [
                'params' => [
                    'user' => 'admin',
                    'password' => '<redacted>',
                ],
            ],
        ]));
    }

    public function testQuery(): void
    {
        $connection = $this->driver->connect([]);
        $connection->query('SELECT 1');

        self::assertTrue($this->logger->hasDebug([
            'message' => 'Executing query: {sql}',
            'context' => ['sql' => 'SELECT 1'],
        ]));
    }

    public function testExec(): void
    {
        $connection = $this->driver->connect([]);
        $connection->exec('DROP DATABASE doctrine');

        self::assertTrue($this->logger->hasDebug([
            'message' => 'Executing statement: {sql}',
            'context' => ['sql' => 'DROP DATABASE doctrine'],
        ]));
    }

    public function testBeginCommitRollback(): void
    {
        $connection = $this->driver->connect([]);
        $connection->beginTransaction();
        $connection->commit();
        $connection->rollBack();

        self::assertTrue($this->logger->hasDebug('Beginning transaction'));
        self::assertTrue($this->logger->hasDebug('Committing transaction'));
        self::assertTrue($this->logger->hasDebug('Rolling back transaction'));
    }

    public function testExecuteStatementWithParameters(): void
    {
        $connection = $this->driver->connect([]);
        $statement  = $connection->prepare('SELECT ?, ?');
        $statement->bindValue(1, 42, ParameterType::INTEGER);

        $statement->execute();

        self::assertTrue($this->logger->hasDebug([
            'message' => 'Executing statement: {sql} (parameters: {params}, types: {types})',
            'context' => [
                'sql' => 'SELECT ?, ?',
                'params' => [1 => 42],
                'types' => [1 => ParameterType::INTEGER],
            ],
        ]));
    }

    public function testExecuteStatementWithNamedParameters(): void
    {
        $connection = $this->driver->connect([]);
        $statement  = $connection->prepare('SELECT :value');
        $statement->bindValue('value', 'Test', ParameterType::STRING);

        $statement->execute();

        self::assertTrue($this->logger->hasDebug([
            'message' => 'Executing statement: {sql} (parameters: {params}, types: {types})',
            'context' => [
                'sql' => 'SELECT :value',
                'params' => ['value' => 'Test'],
                'types' => ['value' => ParameterType::STRING],
            ],
        ]));
    }
}
