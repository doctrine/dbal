<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;

class StatementIteratorTest extends \Doctrine\Tests\DbalTestCase
{
    public function testGettingIteratorDoesNotCallFetch()
    {
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->never())->method('fetch');
        $stmt->expects($this->never())->method('fetchAll');
        $stmt->expects($this->never())->method('fetchColumn');

        $stmtIterator = new StatementIterator($stmt);
        $stmtIterator->getIterator();
    }

    public function testIterationCallsFetchOncePerStep()
    {
        $values = ['foo', '', 'bar', '0', 'baz', 0, 'qux', null, 'quz', false, 'impossible'];
        $calls = 0;

        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->exactly(10))
            ->method('fetch')
            ->willReturnCallback(function() use ($values, &$calls) {
                $value = $values[$calls];
                $calls++;
                return $value;
            });

        $stmtIterator = new StatementIterator($stmt);
        foreach ($stmtIterator as $i => $_) {
            $this->assertEquals($i + 1, $calls);
        }
    }
}
