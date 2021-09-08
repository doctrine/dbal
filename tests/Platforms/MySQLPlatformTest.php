<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\TransactionIsolationLevel;

class MySQLPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQLPlatform();
    }

    protected function createComparator(): Comparator
    {
        return new MySQL\Comparator($this->platform);
    }

    public function testHasCorrectDefaultTransactionIsolationLevel(): void
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel()
        );
    }
}
