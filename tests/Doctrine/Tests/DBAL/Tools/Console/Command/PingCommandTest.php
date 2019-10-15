<?php

namespace Doctrine\Tests\DBAL\Tools\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\Command\PingCommand;
use Doctrine\DBAL\Tools\Console\ConsoleRunner;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PingCommandTest extends TestCase
{
    /** @var CommandTester */
    private $commandTester;
    /** @var PingCommand */
    private $command;

    /** @var Connection */
    private $connectionMock;

    protected function setUp() : void
    {
        $application = new Application();
        $application->add(new PingCommand());

        $this->command       = $application->find('dbal:ping');
        $this->commandTester = new CommandTester($this->command);

        $this->connectionMock = $this->createMock(Connection::class);

        $helperSet = ConsoleRunner::createHelperSet($this->connectionMock);
        $this->command->setHelperSet($helperSet);
    }

    public function testConnectionWorking() : void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('ping')
            ->willReturn(true);

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testConnectionNotWorking() : void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('ping')
            ->willReturn(false);

        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertSame("Ping failed\n", $this->commandTester->getDisplay(true));
    }

    public function testConnectionErrors() : void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('ping')
            ->willThrowException(new PDOException('Connection failed'));

        $this->commandTester->execute([]);

        self::assertSame(2, $this->commandTester->getStatusCode());
        self::assertSame("Ping failed: Connection failed\n", $this->commandTester->getDisplay(true));
    }

    public function testConnectionNotWorkingLoop() : void
    {
        $this->connectionMock
            ->expects($this->exactly(3))
            ->method('ping')
            ->willReturn(false);

        $this->commandTester->execute([
            '--limit' => '3',
            '--sleep' => '0',
        ]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertSame("Ping failed\nPing failed\nPing failed\n", $this->commandTester->getDisplay(true));
    }

    public function testConnectionStartsWorking() : void
    {
        $this->connectionMock
            ->expects($this->exactly(3))
            ->method('ping')
            ->willReturnOnConsecutiveCalls(false, false, true);

        $this->commandTester->execute([
            '--limit' => '5',
            '--sleep' => '0',
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertSame("Ping failed\nPing failed\n", $this->commandTester->getDisplay(true));
    }

    public function testInvalidLimit() : void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Option "limit" must contain a positive integer value');

        $this->commandTester->execute(['--limit' => '-1']);

        self::assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testInvalidLimitNum() : void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Option "limit" must contain a positive integer value');

        $this->commandTester->execute(['--limit' => 'foo']);

        self::assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testInvalidSleep() : void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Option "sleep" must contain a positive integer value');

        $this->commandTester->execute(['--sleep' => '-1']);

        self::assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testInvalidSleepNum() : void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Option "sleep" must contain a positive integer value');

        $this->commandTester->execute(['--sleep' => 'foo']);

        self::assertSame(1, $this->commandTester->getStatusCode());
    }
}
