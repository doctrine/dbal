<?php

namespace Doctrine\DBAL\Tests\Driver\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PHPUnit\Framework\TestCase;

final class AbstractStatementMiddlewareTest extends TestCase
{
    public function testExecute(): void
    {
        $result    = $this->createMock(Result::class);
        $statement = $this->createMock(Statement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with(['foo' => 'bar'])
            ->willReturn($result);

        self::assertSame($result, $this->createMiddleware($statement)->execute(['foo' => 'bar']));
    }

    private function createMiddleware(Statement $statement): AbstractStatementMiddleware
    {
        return new class ($statement) extends AbstractStatementMiddleware {
        };
    }
}
