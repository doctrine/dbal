<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result;
use PHPUnit\Framework\TestCase;

final class AbstractResultMiddlewareTest extends TestCase
{
    public function testFetchAssociative(): void
    {
        $row    = ['field' => 'value', 'another_field' => 42];
        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn($row);

        self::assertSame($row, $this->createMiddleware($result)->fetchAssociative());
    }

    private function createMiddleware(Result $result): AbstractResultMiddleware
    {
        return new class ($result) extends AbstractResultMiddleware {
        };
    }
}
