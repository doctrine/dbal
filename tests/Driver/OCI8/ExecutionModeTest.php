<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\ExecutionMode;
use PHPUnit\Framework\TestCase;

final class ExecutionModeTest extends TestCase
{
    private ExecutionMode $mode;

    protected function setUp(): void
    {
        $this->mode = new ExecutionMode();
    }

    public function testDefaultAutoCommitStatus(): void
    {
        self::assertTrue($this->mode->isAutoCommitEnabled());
    }

    public function testChangeAutoCommitStatus(): void
    {
        $this->mode->disableAutoCommit();
        self::assertFalse($this->mode->isAutoCommitEnabled());

        $this->mode->enableAutoCommit();
        self::assertTrue($this->mode->isAutoCommitEnabled());
    }
}
