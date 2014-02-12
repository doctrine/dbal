<?php

namespace Doctrine\Tests\DBAL\Driver\Mysqli;

use Doctrine\Tests\DbalTestCase;

class MysqliConnectionTest extends DbalTestCase
{
    /**
     * The mysqli driver connection mock under test.
     *
     * @var \Doctrine\DBAL\Driver\Mysqli\MysqliConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        parent::setUp();

        $this->connectionMock = $this->getMockBuilder('Doctrine\DBAL\Driver\Mysqli\MysqliConnection')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        $this->assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}
