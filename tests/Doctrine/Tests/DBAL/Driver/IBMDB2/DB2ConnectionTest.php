<?php

namespace Doctrine\Tests\DBAL\Driver\IBMDB2;

use Doctrine\Tests\DbalTestCase;

class DB2ConnectionTest extends DbalTestCase
{
    /**
     * The ibm_db2 driver connection mock under test.
     *
     * @var \Doctrine\DBAL\Driver\IBMDB2\DB2Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        parent::setUp();

        $this->connectionMock = $this->getMockBuilder('Doctrine\DBAL\Driver\IBMDB2\DB2Connection')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        $this->assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}
