<?php

namespace Doctrine\Tests\DBAL\Tools\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\ConsoleRunner;
use LogicException;
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

    /** @var Connection */
    private $connectionMock;

    protected function setUp()
    {
        $application = new Application();
        $application->add(new RunSqlCommand());

        $this->command       = $application->find('dbal:run-sql');
        $this->commandTester = new CommandTester($this->command);

        $this->connectionMock = $this->createMock(Connection::class);
        $this->connectionMock->method('fetchAll')
            ->willReturn([[1]]);
        $this->connectionMock->method('executeUpdate')
            ->willReturn(42);

        $helperSet = ConsoleRunner::createHelperSet($this->connectionMock);
        $this->command->setHelperSet($helperSet);
    }

    public function testMissingSqlArgument()
    {
        try {
            $this->commandTester->execute([
                'command' => $this->command->getName(),
                'sql' => null,
            ]);
            $this->fail('Expected a runtime exception when omitting sql argument');
        } catch (RuntimeException $e) {
            self::assertContains("Argument 'SQL", $e->getMessage());
        }
    }

    public function testIncorrectDepthOption()
    {
        try {
            $this->commandTester->execute([
                'command' => $this->command->getName(),
                'sql' => 'SELECT 1',
                '--depth' => 'string',
            ]);
            $this->fail('Expected a logic exception when executing with a stringy depth');
        } catch (LogicException $e) {
            self::assertContains("Option 'depth'", $e->getMessage());
        }
    }

    public function testSelectStatementsPrintsResult()
    {
        $this->expectConnectionFetchAll();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'SELECT 1',
        ]);

        self::assertRegExp('@int.*1.*@', $this->commandTester->getDisplay());
        self::assertRegExp('@array.*1.*@', $this->commandTester->getDisplay());
    }

    public function testUpdateStatementsPrintsAffectedLines()
    {
        $this->expectConnectionExecuteUpdate();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => 'UPDATE foo SET bar = 42',
        ]);

        self::assertRegExp('@int.*42.*@', $this->commandTester->getDisplay());
        self::assertNotRegExp('@array.*1.*@', $this->commandTester->getDisplay());
    }

    private function expectConnectionExecuteUpdate()
    {
        $this->connectionMock
            ->expects($this->exactly(1))
            ->method('executeUpdate');
        $this->connectionMock
            ->expects($this->exactly(0))
            ->method('fetchAll');
    }

    private function expectConnectionFetchAll()
    {
        $this->connectionMock
            ->expects($this->exactly(0))
            ->method('executeUpdate');
        $this->connectionMock
            ->expects($this->exactly(1))
            ->method('fetchAll');
    }

    public function testStatementsWithFetchResultPrintsResult()
    {
        $this->expectConnectionFetchAll();

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'sql' => '"WITH bar as (SELECT 1) SELECT * FROM bar',
            '--force-fetch' => true,
        ]);

        self::assertRegExp('@int.*1.*@', $this->commandTester->getDisplay());
        self::assertRegExp('@array.*1.*@', $this->commandTester->getDisplay());
    }
}
