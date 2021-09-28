<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class SchemaTest extends FunctionalTestCase
{
    public function testSchemaName(): void
    {
        $schema = new Schema([], [], $this->connection->createSchemaManager()->createSchemaConfig());
        self::assertNotEmpty($schema->getName());
    }
}
