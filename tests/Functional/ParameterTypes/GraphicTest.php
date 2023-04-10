<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\ParameterTypes;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

class GraphicTest extends FunctionalTestCase
{
    public function testGraphicBinding(): void
    {
        if (! TestUtil::isDriverOneOf('ibm_db2')) {
            self::markTestSkipped('Driver does not support graphic string binding');
        }

        $this->dropTableIfExists('graphic_table');

        $this->connection->executeStatement(
            'CREATE TABLE graphic_table(id INT, graphic_val GRAPHIC(4), vargraphic_val VARGRAPHIC(2))',
        );

        $graphicValue    = '😀';
        $vargraphicValue = '🙃';

        $this->connection->insert(
            'graphic_table',
            ['id' => 1, 'graphic_val' => $graphicValue, 'vargraphic_val' => $vargraphicValue],
            ['id' => Types::INTEGER, 'graphic_val' => Types::TEXT, 'vargraphic_val' => Types::TEXT],
        );

        $result = $this->connection->fetchNumeric(
            'SELECT STRIP(graphic_val), vargraphic_val FROM graphic_table WHERE id = ?',
            [1],
        );

        self::assertIsArray($result);
        self::assertSame($graphicValue, $result[0]);
        self::assertSame($vargraphicValue, $result[1]);
    }
}
