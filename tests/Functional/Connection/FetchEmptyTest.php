<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Connection;

use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sprintf;

final class FetchEmptyTest extends FunctionalTestCase
{
    private string $query;

    public function setUp(): void
    {
        $this->query = sprintf(
            'SELECT * FROM (%s) t WHERE 1 = 0',
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL('1 c'),
        );
    }

    public function testFetchAssociative(): void
    {
        self::assertFalse($this->connection->fetchAssociative($this->query));
    }

    public function testFetchNumeric(): void
    {
        self::assertFalse($this->connection->fetchNumeric($this->query));
    }

    public function testFetchOne(): void
    {
        self::assertFalse($this->connection->fetchOne($this->query));
    }

    public function testFetchAllAssociative(): void
    {
        self::assertSame([], $this->connection->fetchAllAssociative($this->query));
    }

    public function testFetchAllNumeric(): void
    {
        self::assertSame([], $this->connection->fetchAllNumeric($this->query));
    }

    public function testFetchFirstColumn(): void
    {
        self::assertSame([], $this->connection->fetchFirstColumn($this->query));
    }
}
