<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;

class Db2SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof DB2Platform;
    }
}
