<?php

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\IntegerType;

final class SchemaTest extends FunctionalTestCase
{
    public function testCreatePrimaryKeyWithTextType(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof MySQLPlatform) {
            self::markTestSkipped('Test is for MySQL.');
        }

        $this->dropTableIfExists('my_table');

        $table    = new Table('my_table', [new Column('id', new TextType()), new Column('id_2', new IntegerType())]);

        $table->setPrimaryKey(['id','id_2']);

        $schema = new Schema([$table]);
        foreach ($schema->toSql($platform) as $sql) {
            $this->connection->executeStatement( $sql );
        }

        $result = $this->connection->fetchAssociative(
            'SELECT column_default FROM information_schema.columns WHERE table_name = ?',
            ['my_table']
        );

        $this->assertNotFalse($result);

        $result = $this->connection->fetchAssociative(
            'SELECT table_name FROM information_schema.key_column_usage WHERE table_name = ? AND CONSTRAINT_NAME = "PRIMARY"',
            ['my_table']
        );
        $this->assertNotFalse($result);
    }
}
