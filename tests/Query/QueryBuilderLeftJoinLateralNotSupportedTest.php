<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\SQL\Builder\DefaultJoinLateralSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use function sprintf;

class QueryBuilderLeftJoinLateralNotSupportedTest extends TestCase
{
    private Connection&MockObject $conn;
    private AbstractPlatform&Stub $platform;

    protected function setUp(): void
    {
        $this->conn = $this->createMock(Connection::class);

        $this->conn->method('createQueryBuilder')
            ->willReturnCallback(fn () => new QueryBuilder($this->conn));

        $this->platform = self::createStub(AbstractPlatform::class);
        $this->platform->method('createSelectSQLBuilder')
            ->willReturn(new DefaultSelectSQLBuilder($this->platform, null, null));
        $this->platform->method('createJoinLateralSQLBuilder')
            ->willReturn(new DefaultJoinLateralSQLBuilder($this->platform));

        $this->conn->method('getDatabasePlatform')
            ->willReturn($this->platform);
    }

    public function testPlatformDoesNotSupportJoinLateral(): void
    {
        $this->platform->method('getJoinLateralSQL')
            ->willThrowException(NotSupported::new(AbstractPlatform::class . '::getJoinLateralSQL'));

        $qb = $this->conn->createQueryBuilder();
        $qb->select('for_update.id', 'jl.lateral_field')
            ->from('for_update')
            ->leftJoinLateral('for_update', 'SELECT 1 as lateral_field', 'jl');

        self::expectException(NotSupported::class);
        self::expectExceptionMessage(sprintf(
            'Operation "%s" is not supported by platform.',
            AbstractPlatform::class . '::getJoinLateralSQL',
        ));
        $qb->executeQuery();
    }

    public function testPlatformDoesNotSupportJoinLateralConditions(): void
    {
        $this->platform->method('getJoinLateralConditionsSQL')
            ->willThrowException(NotSupported::new(AbstractPlatform::class . '::getJoinLateralConditionsSQL'));

        $qb = $this->conn->createQueryBuilder();
        $qb->select('for_update.id', 'lateral_condition.lateral_id')
            ->from('for_update')
            ->leftJoinLateral(
                'for_update',
                'SELECT id AS lateral_id FROM for_update',
                'lateral_condition',
                'for_update.id = lateral_condition.lateral_id',
            );

        self::expectException(NotSupported::class);
        self::expectExceptionMessage(sprintf(
            'Operation "%s" is not supported by platform.',
            AbstractPlatform::class . '::getJoinLateralConditionsSQL',
        ));
        $qb->executeQuery();
    }
}
