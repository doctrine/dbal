<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use PHPUnit\Framework\TestCase;

class InvalidColumnIndexTest extends TestCase
{
    public function testNew() : void
    {
        $exception = InvalidColumnIndex::new(5, 1);

        self::assertInstanceOf(DBALException::class, $exception);
        self::assertSame('Invalid column index 5. The statement result contains 1 column.', $exception->getMessage());
    }

    public function testNewPlural() : void
    {
        $exception = InvalidColumnIndex::new(5, 2);

        self::assertInstanceOf(DBALException::class, $exception);
        self::assertSame('Invalid column index 5. The statement result contains 2 columns.', $exception->getMessage());
    }
}
