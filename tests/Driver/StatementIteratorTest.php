<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Traversable;

class StatementIteratorTest extends TestCase
{
    public function testIteratorIterationCallsFetchOncePerStep() : void
    {
        $stmt = $this->createMock(Statement::class);

        $calls = 0;
        $this->configureStatement($stmt, $calls);

        $stmtIterator = new StatementIterator($stmt);

        $this->assertIterationCallsFetchOncePerStep($stmtIterator, $calls);
    }

    private function configureStatement(MockObject $stmt, int &$calls) : void
    {
        $values = ['foo', '', 'bar', '0', 'baz', 0, 'qux', null, 'quz', false, 'impossible'];
        $calls  = 0;

        $stmt->expects(self::exactly(10))
            ->method('fetch')
            ->willReturnCallback(static function () use ($values, &$calls) {
                $value = $values[$calls];
                $calls++;

                return $value;
            });
    }

    /**
     * @param Traversable<int, mixed> $iterator
     */
    private function assertIterationCallsFetchOncePerStep(Traversable $iterator, int &$calls) : void
    {
        foreach ($iterator as $i => $_) {
            self::assertEquals($i + 1, $calls);
        }
    }
}
