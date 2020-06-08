<?php

namespace Doctrine\DBAL\Tests\Tools\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\ConsoleRunner;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RunSqlCommandTest extends TestCase
{
    /** @var CommandTester */
    private $commandTester;
    /** @var RunSqlCommand */
    private $command;

    /** @var Connection&MockObject */
    private $connectionMock;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new RunSqlCommand());

        $this->command       = $application->find('dbal:run-sql');
        $this->commandTester = new CommandTester($this->command);

        $this->connectionMock = $this->createMock(Connection::class);
        $this->connectionMock->method('fetchAllAssociative')
            ->willReturn([[1]]);
        $this->connectionMock->method('executeUpdate')
            ->willReturn(42);

        $helperSet = ConsoleRunner::createHelperSet($this->connectionMock);
        $this->command->setHelperSet($helperSet);
    }

    public function testMissingSqlArgument(): void
    {
        try {
            $this->commandTester->execute([
                'command' => $this->command->getName(),
                'sql' => null,
            ]);
            self::fail('Expected a runtime exception when omitting sql argument');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("Argument 'SQL", $e->getMessage());
        }
    }

    public function testIncorrectDepthOption(): void
    {
        try {
            $this->commandTester->execute([
                'command' => $this->command->getName(),
                'sql' => 'SELECT 1',
                '--depth' => 'string',
            ]);
            self::fail('Expected a logic exception when executing with a stringy depth');
        } catch (LogicException $e) {
            self::assertStringContainsString("Option 'depth'", $e->getMessage());
        }
    }

    public function testSelectStatementsPrintsResult(): void
    {
        $this->expectConnectionFetchAllAssociative();

        $exitCode = $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'SELECT 1',
        ]);
        self::assertSame(0, $exitCode);

        self::assertMatchesRegularExpression('@int.*1.*@', $this->commandTester->getDisplay());
        self::assertMatchesRegularExpression('@array.*1.*@', $this->commandTester->getDisplay());
    }

    public function testUpdateStatementsPrintsAffectedLines(): void
    {
        $this->expectConnectionExecuteUpdate();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'UPDATE foo SET bar = 42',
        ]);

        self::assertMatchesRegularExpression('@int.*42.*@', $this->commandTester->getDisplay());
        self::assertDoesNotMatchRegularExpression('@array.*1.*@', $this->commandTester->getDisplay());
    }

    private function expectConnectionExecuteUpdate(): void
    {
        $this->connectionMock
            ->expects(self::exactly(1))
            ->method('executeUpdate');
        $this->connectionMock
            ->expects(self::exactly(0))
            ->method('fetchAllAssociative');
    }

    private function expectConnectionFetchAllAssociative(): void
    {
        $this->connectionMock
            ->expects(self::exactly(0))
            ->method('executeUpdate');
        $this->connectionMock
            ->expects(self::exactly(1))
            ->method('fetchAllAssociative');
    }

    public function testStatementsWithFetchResultPrintsResult(): void
    {
        $this->expectConnectionFetchAllAssociative();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => '"WITH bar as (SELECT 1) SELECT * FROM bar',
            '--force-fetch' => true,
        ]);

        self::assertMatchesRegularExpression('@int.*1.*@', $this->commandTester->getDisplay());
        self::assertMatchesRegularExpression('@array.*1.*@', $this->commandTester->getDisplay());
    }
}
