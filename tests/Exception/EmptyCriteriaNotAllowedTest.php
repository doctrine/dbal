<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Exception;

use Doctrine\DBAL\Exception\EmptyCriteriaNotAllowed;
use PHPUnit\Framework\TestCase;

class EmptyCriteriaNotAllowedTest extends TestCase
{
    public function testNew(): void
    {
        $exception = EmptyCriteriaNotAllowed::new();

        self::assertSame('Empty criteria was used, expected non-empty criteria.', $exception->getMessage());
    }
}
