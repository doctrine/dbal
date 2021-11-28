<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Logging;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MiddlewareTest extends TestCase
{
    /** @var Driver */
    private $driver;

    /** @var LoggerInterface&MockObject */
    private $logger;

    public function setUp(): void
    {
        $connection = $this->createMock(Connection::class);

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')
            ->willReturn($connection);

        $this->logger = $this->createMock(LoggerInterface::class);

        $middleware   = new Middleware($this->logger);
        $this->driver = $middleware->wrap($driver);
    }

    public function testConnectAndDisconnect(): void
    {
        $this->logger->expects(self::exactly(2))
            ->method('info')
            ->withConsecutive(
                [
                    'Connecting with parameters {params}',
                    [
                        'params' => [
                            'username' => 'admin',
                            'password' => '<redacted>',
                        ],
                    ],
                ],
                ['Disconnecting', []],
            );

        $this->driver->connect([
            'username' => 'admin',
            'password' => 'Passw0rd!',
        ]);
    }

    public function testQuery(): void
    {
        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Executing query: {sql}', ['sql' => 'SELECT 1']);

        $connection = $this->driver->connect([]);
        $connection->query('SELECT 1');
    }

    public function testExec(): void
    {
        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Executing statement: {sql}', ['sql' => 'DROP DATABASE doctrine']);

        $connection = $this->driver->connect([]);
        $connection->exec('DROP DATABASE doctrine');
    }

    public function testBeginCommitRollback(): void
    {
        $this->logger->expects(self::exactly(3))
            ->method('debug')
            ->withConsecutive(
                ['Beginning transaction'],
                ['Committing transaction'],
                ['Rolling back transaction'],
            );

        $connection = $this->driver->connect([]);
        $connection->beginTransaction();
        $connection->commit();
        $connection->rollBack();
    }

    public function testExecuteStatementWithUntypedParameters(): void
    {
        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Executing statement: {sql} (parameters: {params}, types: {types})', [
                'sql' => 'SELECT ?',
                'params' => [42],
                'types' => [],
            ]);

        $connection = $this->driver->connect([]);
        $statement  = $connection->prepare('SELECT ?');
        $statement->execute([42]);
    }

    public function testExecuteStatementWithTypedParameters(): void
    {
        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Executing statement: {sql} (parameters: {params}, types: {types})', [
                'sql' => 'SELECT ?, ?',
                'params' => [1 => 42, 2 => 'Test'],
                'types' => [1 => ParameterType::INTEGER, 2 => ParameterType::STRING],
            ]);

        $connection = $this->driver->connect([]);
        $statement  = $connection->prepare('SELECT ?, ?');
        $statement->bindValue(1, 42, ParameterType::INTEGER);
        $statement->bindParam(2, $byRef, ParameterType::STRING);

        $byRef = 'Test';
        $statement->execute();
    }

    public function testExecuteStatementWithNamedParameters(): void
    {
        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Executing statement: {sql} (parameters: {params}, types: {types})', [
                'sql' => 'SELECT :value',
                'params' => ['value' => 'Test'],
                'types' => ['value' => ParameterType::STRING],
            ]);

        $connection = $this->driver->connect([]);
        $statement  = $connection->prepare('SELECT :value');
        $statement->bindValue('value', 'Test');

        $statement->execute();
    }
}
