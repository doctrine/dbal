<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Exception;

use Doctrine\DBAL\Exception\EmptyCriteriaNotAllowed;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmptyCriteriaNotAllowedTest extends TestCase
{
    public function testNew() : void
    {
        $exception = EmptyCriteriaNotAllowed::new();

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame('Empty criteria was used, expected non-empty criteria', $exception->getMessage());
    }
}
