<?php

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;

use function sprintf;

class CharLengthExpressionTest extends DbalFunctionalTestCase
{
    /**
     * @throws Exception
     */
    public function testCharLengthExpressionNotSupport(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof SqlitePlatform) {
            self::markTestSkipped('Test is for sqlite only');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Operation \'Doctrine\DBAL\Platforms\AbstractPlatform::getCharLengthExpression\' 
            is not supported by platform'
        );
        $platform->getCharLengthExpression('testColumn');
    }

    /**
     * @throws Exception
     * @throws DriverException
     */
    public function testCharLengthExpression(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof MySqlPlatform) {
            self::markTestSkipped('Test is for mysql only');
        }

        $table = new Table('char_length_expression_test');
        $table->addColumn('testColumn1', Types::STRING);
        $table->addColumn('testColumn2', Types::STRING);
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
        $this->connection->insert('char_length_expression_test', [
            'testColumn1' => '€',
            'testColumn2' => 'str',
        ]);

        $sql = sprintf('SELECT %s FROM char_length_expression_test', $platform->getCharLengthExpression('testColumn1'));
        self::assertSame(1, $this->connection->executeQuery($sql)->fetchColumn());
        $sql = sprintf('SELECT %s FROM char_length_expression_test', $platform->getCharLengthExpression('testColumn2'));
        self::assertSame(3, $this->connection->executeQuery($sql)->fetchColumn());
    }
}
