<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\IBMDB2\DB2Statement;
use Doctrine\DBAL\Driver\Mysqli\MysqliStatement;
use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereStatement;
use Doctrine\DBAL\Driver\SQLSrv\SQLSrvStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Portability\Statement as PortabilityStatement;
use Doctrine\Tests\DbalTestCase;
use IteratorAggregate;
use PHPUnit\Framework\MockObject\MockObject;
use Traversable;
use function extension_loaded;

class StatementIteratorTest extends DbalTestCase
{
    /**
     * @dataProvider statementProvider()
     */
    public function testGettingIteratorDoesNotCallFetch(string $class) : void
    {
        /** @var IteratorAggregate|MockObject $stmt */
        $stmt = $this->createPartialMock($class, ['fetch', 'fetchAll', 'fetchColumn']);
        $stmt->expects($this->never())->method('fetch');
        $stmt->expects($this->never())->method('fetchAll');
        $stmt->expects($this->never())->method('fetchColumn');

        $stmt->getIterator();
    }

    public function testIteratorIterationCallsFetchOncePerStep() : void
    {
        $stmt = $this->createMock(Statement::class);

        $calls = 0;
        $this->configureStatement($stmt, $calls);

        $stmtIterator = new StatementIterator($stmt);

        $this->assertIterationCallsFetchOncePerStep($stmtIterator, $calls);
    }

    /**
     * @dataProvider statementProvider()
     */
    public function testStatementIterationCallsFetchOncePerStep(string $class) : void
    {
        $stmt = $this->createPartialMock($class, ['fetch']);

        $calls = 0;
        $this->configureStatement($stmt, $calls);
        $this->assertIterationCallsFetchOncePerStep($stmt, $calls);
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

    /**
     * @return string[][]
     */
    public static function statementProvider() : iterable
    {
        if (extension_loaded('ibm_db2')) {
            yield [DB2Statement::class];
        }

        yield [MysqliStatement::class];

        if (extension_loaded('oci8')) {
            yield [OCI8Statement::class];
        }

        yield [PortabilityStatement::class];
        yield [SQLAnywhereStatement::class];

        if (extension_loaded('sqlsrv')) {
            yield [SQLSrvStatement::class];
        }
    }
}
