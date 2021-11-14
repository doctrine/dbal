<?php

namespace Doctrine\Tests\DBAL\ForwardCompatibility;

use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ForwardCompatibility\Result;
use Doctrine\DBAL\ParameterType;
use PDO;
use PHPUnit\Framework\TestCase;
use Traversable;

use function iterator_to_array;

class ResultTest extends TestCase
{
    /** @var Result */
    private $instance;

    public function setUp(): void
    {
        $this->instance = new Result(
            new ArrayStatement([
                [
                    'row1col1' => 'row1col1value',
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                [
                    'row2col1' => 'row2col1value',
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                [
                    'row3col1' => 'row3col1value',
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ])
        );
    }

    public function testEnsureWithResult(): void
    {
        $instance = Result::ensure($this->instance);
        $this->assertSame($this->instance, $instance, 'Result is not wrapped twice');
    }

    public function testEnsureWithResultStatement(): void
    {
        $instance = Result::ensure($this->createMock(Driver\ResultStatement::class));
        $this->assertInstanceOf(Result::class, $instance);
    }

    public function testEnsureWithStatement(): void
    {
        $instance = Result::ensure($this->createMock(Driver\Statement::class));
        $this->assertInstanceOf(Result::class, $instance);
    }

    public function testIsTraversable(): void
    {
        $this->instance->setFetchMode(PDO::FETCH_ASSOC);

        $data = [];

        foreach ($this->instance as $row) {
            $data[] = $row;
        }

        $this->assertInstanceOf(Traversable::class, $this->instance);
        $this->assertSame(
            [
                [
                    'row1col1' => 'row1col1value',
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                [
                    'row2col1' => 'row2col1value',
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                [
                    'row3col1' => 'row3col1value',
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ],
            $data
        );
    }

    public function testFetchWithPdoAssoc(): void
    {
        $this->assertSame(
            [
                'row1col1' => 'row1col1value',
                'row1col2' => 'row1col2value',
                'row1col3' => 'row1col3value',
            ],
            $this->instance->fetch(PDO::FETCH_ASSOC)
        );
    }

    public function testFetchAllWithPdoAssoc(): void
    {
        $this->assertSame(
            [
                [
                    'row1col1' => 'row1col1value',
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                [
                    'row2col1' => 'row2col1value',
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                [
                    'row3col1' => 'row3col1value',
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ],
            $this->instance->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testFetchColumn(): void
    {
        $this->assertSame(
            'row1col2value',
            $this->instance->fetchColumn(1)
        );
    }

    public function testFetchNumeric(): void
    {
        $this->assertSame(
            [
                'row1col1value',
                'row1col2value',
                'row1col3value',
            ],
            $this->instance->fetchNumeric()
        );
    }

    public function testFetchAssociative(): void
    {
        $this->assertSame(
            [
                'row1col1' => 'row1col1value',
                'row1col2' => 'row1col2value',
                'row1col3' => 'row1col3value',
            ],
            $this->instance->fetchAssociative()
        );
    }

    public function testFetchOne(): void
    {
        $this->assertSame(
            'row1col1value',
            $this->instance->fetchOne()
        );
    }

    public function testAllNumeric(): void
    {
        $this->assertSame(
            [
                [
                    'row1col1value',
                    'row1col2value',
                    'row1col3value',
                ],
                [
                    'row2col1value',
                    'row2col2value',
                    'row2col3value',
                ],
                [
                    'row3col1value',
                    'row3col2value',
                    'row3col3value',
                ],
            ],
            $this->instance->fetchAllNumeric()
        );
    }

    public function testAllAssociative(): void
    {
        $this->assertSame(
            [
                [
                    'row1col1' => 'row1col1value',
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                [
                    'row2col1' => 'row2col1value',
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                [
                    'row3col1' => 'row3col1value',
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ],
            $this->instance->fetchAllAssociative()
        );
    }

    public function testFetchAllKeyValue(): void
    {
        $this->assertSame(
            [
                'row1col1value' => 'row1col2value',
                'row2col1value' => 'row2col2value',
                'row3col1value' => 'row3col2value',
            ],
            $this->instance->fetchAllKeyValue()
        );
    }

    public function testFetchAllAssociativeIndexed(): void
    {
        $this->assertSame(
            [
                'row1col1value' => [
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                'row2col1value' => [
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                'row3col1value' => [
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ],
            $this->instance->fetchAllAssociativeIndexed()
        );
    }

    public function testFetchFirstColumn(): void
    {
        $this->assertSame(
            [
                'row1col1value',
                'row2col1value',
                'row3col1value',
            ],
            $this->instance->fetchFirstColumn()
        );
    }

    public function testIterateNumeric(): void
    {
        $this->assertSame(
            [
                [
                    0 => 'row1col1value',
                    1 => 'row1col2value',
                    2 => 'row1col3value',
                ],
                [
                    0 => 'row2col1value',
                    1 => 'row2col2value',
                    2 => 'row2col3value',
                ],
                [
                    0 => 'row3col1value',
                    1 => 'row3col2value',
                    2 => 'row3col3value',
                ],
            ],
            iterator_to_array($this->instance->iterateNumeric())
        );
    }

    public function testIterateAssociative(): void
    {
        $this->assertSame(
            [
                [
                    'row1col1' => 'row1col1value',
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                [
                    'row2col1' => 'row2col1value',
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                [
                    'row3col1' => 'row3col1value',
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ],
            iterator_to_array($this->instance->iterateAssociative())
        );
    }

    public function testIterateKeyValue(): void
    {
        $this->assertSame(
            [
                'row1col1value' => 'row1col2value',
                'row2col1value' => 'row2col2value',
                'row3col1value' => 'row3col2value',
            ],
            iterator_to_array($this->instance->iterateKeyValue())
        );
    }

    public function testIterateAssociativeIndexed(): void
    {
        $this->assertSame(
            [
                'row1col1value' => [
                    'row1col2' => 'row1col2value',
                    'row1col3' => 'row1col3value',
                ],
                'row2col1value' => [
                    'row2col2' => 'row2col2value',
                    'row2col3' => 'row2col3value',
                ],
                'row3col1value' => [
                    'row3col2' => 'row3col2value',
                    'row3col3' => 'row3col3value',
                ],
            ],
            iterator_to_array($this->instance->iterateAssociativeIndexed())
        );
    }

    public function testIterateColumn(): void
    {
        $this->assertSame(
            [
                'row1col1value',
                'row2col1value',
                'row3col1value',
            ],
            iterator_to_array($this->instance->iterateColumn())
        );
    }

    public function testRowCountIsSupportedByWrappedArrayStatement(): void
    {
        $this->assertSame(3, $this->instance->rowCount());
    }

    public function testRowCountIsNotSupportedByWrappedStatement(): void
    {
        $this->expectExceptionObject(Exception::notSupported('rowCount'));
        $instance = new Result($this->createMock(Driver\ResultStatement::class));
        $instance->rowCount();
    }

    public function testBindValueIsSupportedByWrappedStatement(): void
    {
        $param = ':key';
        $value = 'value';

        $statement = $this->createMock(Driver\Statement::class);
        $statement
            ->expects($this->once())
            ->method('bindValue')
            ->with($param, $value, ParameterType::STRING)
            ->willReturn(true);

        $instance = new Result($statement);

        $this->assertTrue($instance->bindValue($param, $value));
    }

    public function testBindValueIsNotSupportedByWrappedResultStatement(): void
    {
        $this->expectExceptionObject(Exception::notSupported('bindValue'));
        $this->instance->bindValue(':key', 'value');
    }

    public function testBindParamIsSupportedByWrappedStatement(): void
    {
        $param = ':key';
        $value = 'value';

        $statement = $this->createMock(Driver\Statement::class);
        $statement
            ->expects($this->once())
            ->method('bindParam')
            ->with($param, $value, ParameterType::STRING)
            ->willReturn(true);

        $instance = new Result($statement);

        $this->assertTrue($instance->bindParam($param, $value));
    }

    public function testBindParamIsNotSupportedByWrappedResultStatement(): void
    {
        $param = ':key';
        $value = 'value';

        $this->expectExceptionObject(Exception::notSupported('bindParam'));
        $this->instance->bindParam($param, $value);
    }

    public function testErrorCodeIsSupportedByWrappedStatement(): void
    {
        $errorCode = 32;

        $statement = $this->createMock(Driver\Statement::class);
        $statement
            ->expects($this->once())
            ->method('errorCode')
            ->willReturn($errorCode);

        $instance = new Result($statement);

        $this->assertSame($errorCode, $instance->errorCode());
    }

    public function testErrorCodeIsNotSupportedByWrappedResultStatement(): void
    {
        $this->expectExceptionObject(Exception::notSupported('errorCode'));
        $this->instance->errorCode();
    }

    public function testErrorInfoIsSupportedByWrappedStatement(): void
    {
        $errorInfo = ['Some info'];

        $statement = $this->createMock(Driver\Statement::class);
        $statement
            ->expects($this->once())
            ->method('errorInfo')
            ->willReturn($errorInfo);

        $instance = new Result($statement);

        $this->assertSame($errorInfo, $instance->errorInfo());
    }

    public function testErrorInfoIsNotSupportedByWrappedResultStatement(): void
    {
        $this->expectExceptionObject(Exception::notSupported('errorInfo'));
        $this->instance->errorInfo();
    }

    public function testExecuteIsSupportedByWrappedStatement(): void
    {
        $params = [':key' => 'value'];

        $statement = $this->createMock(Driver\Statement::class);
        $statement
            ->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);

        $instance = new Result($statement);

        $this->assertTrue($instance->execute($params));
    }

    public function testExecuteIsNotSupportedByWrappedResultStatement(): void
    {
        $this->expectExceptionObject(Exception::notSupported('execute'));
        $this->instance->execute([':key' => 'value']);
    }
}
