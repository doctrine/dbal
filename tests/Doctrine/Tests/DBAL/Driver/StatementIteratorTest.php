<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Traversable;

class StatementIteratorTest extends DbalTestCase
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

        $stmt->expects($this->exactly(10))
            ->method('fetch')
            ->willReturnCallback(static function () use ($values, &$calls) {
                $value = $values[$calls];
                $calls++;

                return $value;
            });
    }

    private function assertIterationCallsFetchOncePerStep(Traversable $iterator, int &$calls) : void
    {
        foreach ($iterator as $i => $_) {
            $this->assertEquals($i + 1, $calls);
        }
    }
}
