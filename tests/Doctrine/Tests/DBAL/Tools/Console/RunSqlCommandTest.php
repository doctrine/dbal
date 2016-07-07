<?php

namespace Doctrine\Tests\DBAL\Tools\Console;

use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\ConsoleRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RunSqlCommandTest extends \PHPUnit_Framework_TestCase
{
    /** @var CommandTester */
    private $commandTester;
    /** @var RunSqlCommand */
    private $command;

    private $connectionMock;

    protected function setUp()
    {
        $application = new Application();
        $application->add(new RunSqlCommand());

        $this->command = $application->find('dbal:run-sql');
        $this->commandTester = new CommandTester($this->command);

        $this->connectionMock = $this->createMock('\Doctrine\DBAL\Connection');
        $this->connectionMock->method('fetchAll')
            ->willReturn(array(array(1)));
        $this->connectionMock->method('executeUpdate')
            ->willReturn(42);

        $helperSet = ConsoleRunner::createHelperSet($this->connectionMock);
        $this->command->setHelperSet($helperSet);
    }

    public function testMissingSqlArgument()
    {
        try {
            $this->commandTester->execute(array(
                'command' => $this->command->getName(),
                'sql' => null,
            ));
            $this->fail('Expected a runtime exception when omitting sql argument');
        } catch (\RuntimeException $e) {
            $this->assertContains("Argument 'SQL", $e->getMessage());
        }
    }

    public function testIncorrectDepthOption()
    {
        try {
            $this->commandTester->execute(array(
                'command' => $this->command->getName(),
                'sql' => 'SELECT 1',
                '--depth' => 'string',
            ));
            $this->fail('Expected a logic exception when executing with a stringy depth');
        } catch (\LogicException $e) {
            $this->assertContains("Option 'depth'", $e->getMessage());
        }
    }

    public function testSelectStatementsPrintsResult()
    {
        $this->expectConnectionFetchAll();

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'sql' => 'SELECT 1',
        ));

        $this->assertRegExp('@int.*1.*@', $this->commandTester->getDisplay());
        $this->assertRegExp('@array.*1.*@', $this->commandTester->getDisplay());
    }

    public function testUpdateStatementsPrintsAffectedLines()
    {
        $this->expectConnectionExecuteUpdate();

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'sql' => 'UPDATE foo SET bar = 42',
        ));

        $this->assertRegExp('@int.*42.*@', $this->commandTester->getDisplay());
        $this->assertNotRegExp('@array.*1.*@', $this->commandTester->getDisplay());
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

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'sql' => '"WITH bar as (SELECT 1) SELECT * FROM bar',
            '--force-fetch' => true,
        ));

        $this->assertRegExp('@int.*1.*@', $this->commandTester->getDisplay());
        $this->assertRegExp('@array.*1.*@', $this->commandTester->getDisplay());
    }
}
