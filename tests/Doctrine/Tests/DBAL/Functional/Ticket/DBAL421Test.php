<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

/**
 * @group DBAL-421
 */
class DBAL421Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $platform = $this->_conn->getDatabasePlatform()->getName();
        if (!in_array($platform, array('mysql', 'sqlite'))) {
            $this->markTestSkipped('Currently restricted to MySQL and SQLite.');
        }
    }

    public function testGuidShouldMatchPattern()
    {
        $guid = $this->_conn->query($this->getSelectGuidSql())->fetchColumn();
        $pattern = '/[0-9A-F]{8}\-[0-9A-F]{4}\-[0-9A-F]{4}\-[8-9A-B][0-9A-F]{3}\-[0-9A-F]{12}/i';
        $this->assertEquals(1, preg_match($pattern, $guid), "GUID does not match pattern");
    }

    /**
     * This test does (of course) not proof that all generated GUIDs are
     * random, it should however provide some basic confidence.
     */
    public function testGuidShouldBeRandom()
    {
        $statement = $this->_conn->prepare($this->getSelectGuidSql());
        $guids = array();

        for ($i = 0; $i < 99; $i++) {
            $statement->execute();
            $guid = $statement->fetchColumn();
            $this->assertNotContains($guid, $guids, "Duplicate GUID detected");
            $guids[] = $guid;
        }

        $statement->closeCursor();
    }

    private function getSelectGuidSql()
    {
        return "SELECT " . $this->_conn->getDatabasePlatform()->getGuidExpression();
    }
}
