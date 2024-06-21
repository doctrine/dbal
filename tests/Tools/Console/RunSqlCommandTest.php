<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Tools\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function str_replace;

class RunSqlCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private RunSqlCommand $command;
    private Connection&MockObject $connectionMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->command        = new RunSqlCommand(new SingleConnectionProvider($this->connectionMock));

        (new Application())->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testMissingSqlArgument(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Argument "sql" is required in order to execute this command correctly.');

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => null,
        ]);
    }

    public function testSelectStatementsPrintsResult(): void
    {
        $this->expectConnectionFetchAllAssociative();

        $exitCode = $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'SELECT 1',
        ]);
        self::assertSame(0, $exitCode);

        self::assertStringEqualsFile(
            __DIR__ . '/Fixtures/select-1.txt',
            str_replace("\r\n", "\n", $this->commandTester->getDisplay()),
        );
    }

    public function testSelectWithEmptyResultSet(): void
    {
        $this->connectionMock
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with('SELECT foo FROM bar')
            ->willReturn([]);

        $this->connectionMock
            ->expects(self::never())
            ->method('executeStatement');

        $exitCode = $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'SELECT foo FROM bar',
        ]);
        self::assertSame(0, $exitCode);

        self::assertStringContainsString(
            '[OK] The query yielded an empty result set.',
            $this->commandTester->getDisplay(),
        );
    }

    public function testUpdateStatementsPrintsAffectedLines(): void
    {
        $this->expectConnectionExecuteStatement();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'UPDATE foo SET bar = 42',
        ]);

        self::assertStringContainsString('[OK] 42 rows affected.', $this->commandTester->getDisplay(true));
    }

    private function expectConnectionExecuteStatement(): void
    {
        $this->connectionMock
            ->expects(self::once())
            ->method('executeStatement')
            ->willReturn(42);

        $this->connectionMock
            ->expects(self::never())
            ->method('fetchAllAssociative');
    }

    private function expectConnectionFetchAllAssociative(): void
    {
        $this->connectionMock
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([[1]]);

        $this->connectionMock
            ->expects(self::never())
            ->method('executeStatement');
    }

    public function testStatementsWithFetchResultPrintsResult(): void
    {
        $this->expectConnectionFetchAllAssociative();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => '"WITH bar as (SELECT 1) SELECT * FROM bar',
            '--force-fetch' => true,
        ]);

        self::assertStringEqualsFile(
            __DIR__ . '/Fixtures/select-1.txt',
            str_replace("\r\n", "\n", $this->commandTester->getDisplay()),
        );
    }
}
