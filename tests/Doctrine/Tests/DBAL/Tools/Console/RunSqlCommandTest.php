<?php

namespace Doctrine\Tests\DBAL\Tools\Console;

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
            $this->fail('Expected a runtime exception when omitting sql argument');
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
            $this->fail('Expected a logic exception when executing with a stringy depth');
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
        $this->assertSame(0, $exitCode);

        self::assertMatchesRegularExpression('@int.*1.*@', $this->commandTester->getDisplay());
        self::assertMatchesRegularExpression('@array.*1.*@', $this->commandTester->getDisplay());
    }

    public function testUpdateStatementsPrintsAffectedLines(): void
    {
        $this->expectConnectionExecuteStatement();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'UPDATE foo SET bar = 42',
        ]);

        self::assertMatchesRegularExpression('@int.*42.*@', $this->commandTester->getDisplay());
        self::assertDoesNotMatchRegularExpression('@array.*1.*@', $this->commandTester->getDisplay());
    }

    private function expectConnectionExecuteStatement(): void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(42);

        $this->connectionMock
            ->expects($this->never())
            ->method('fetchAllAssociative');
    }

    private function expectConnectionFetchAllAssociative(): void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([[1]]);

        $this->connectionMock
            ->expects($this->never())
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

        self::assertMatchesRegularExpression('@int.*1.*@', $this->commandTester->getDisplay());
        self::assertMatchesRegularExpression('@array.*1.*@', $this->commandTester->getDisplay());
    }
}
