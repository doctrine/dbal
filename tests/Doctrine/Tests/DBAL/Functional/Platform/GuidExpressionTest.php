<?php

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Statement;
use Doctrine\Tests\DbalFunctionalTestCase;
use function end;

class GuidExpressionTest extends DbalFunctionalTestCase
{
    public function testGuidShouldMatchPattern()
    {
        $value = $this->_conn->query($this->getSelectGuidSql())->fetchColumn();

        self::assertRegExp(
            '/[0-9A-F]{8}\-[0-9A-F]{4}\-[0-9A-F]{4}\-[8-9A-B][0-9A-F]{3}\-[0-9A-F]{12}/i',
            $value
        );
    }

    /**
     * This test does (of course) not proof that all generated GUIDs are
     * random, it should however provide some basic confidence.
     */
    public function testGuidShouldBeUniqueAndAscending()
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            $this->markTestSkipped('Currently incorrectly implemented in SQLite platform');
        }

        $statement = $this->_conn->prepare($this->getSelectGuidSql());
        $values    = [$this->generateGuid($statement)];

        for ($i = 0; $i < 99; $i++) {
            $statement->execute();
            $value = $statement->fetchColumn();

            self::assertNotContains($value, $values, 'Duplicate value detected');
            self::assertGreaterThan(end($values), $value);

            $values[] = $value;
        }

        $statement->closeCursor();
    }

    private function generateGuid(Statement $statement) : string
    {
        $statement->execute();

        return $statement->fetchColumn();
    }

    private function getSelectGuidSql()
    {
        $platform = $this->_conn->getDatabasePlatform();

        return $platform->getDummySelectSQL(
            $platform->getGuidExpression()
        );
    }
}
