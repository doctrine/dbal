<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Override;

class Db2SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    #[Override]
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof DB2Platform;
    }

    #[Override]
    public function testIntrospectDatabaseNames(): void
    {
        $this->expectException(Exception::class);

        $this->schemaManager->introspectDatabaseNames();
    }

    #[Override]
    public function getExpectedDefaultSchemaName(): ?string
    {
        return null;
    }
}
