<?php

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySqlPlatform;
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
        if ($platform instanceof MySqlPlatform) {
            self::markTestSkipped('Test is not for mysql');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Operation 'Doctrine\DBAL\Platforms\AbstractPlatform::getCharLengthExpression' is not supported by platform"
        );
        $platform->getCharLengthExpression('testColumn');
    }

    /**
     * @throws Exception
     */
    public function testCharLengthExpression(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof MySqlPlatform) {
            self::markTestSkipped('Test is for mysql only');
        }

        $table = new Table('char_length_expression_test');
        $table->addColumn('testColumn1', Types::STRING);
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
        $this->connection->executeQuery('SET CHARACTER SET utf8');
        $this->connection->insert('char_length_expression_test', ['testColumn1' => '€']);

        $sql = sprintf('SELECT %s FROM char_length_expression_test', $platform->getCharLengthExpression('testColumn1'));
        self::assertSame(1, (int) $this->connection->query($sql)->fetchColumn());
    }
}
