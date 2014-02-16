<?php

namespace Doctrine\Tests\DBAL\Driver\OCI8;

use Doctrine\Tests\DbalTestCase;

class OCI8ConnectionTest extends DbalTestCase
{
    /**
     * The oci8 driver connection mock under test.
     *
     * @var \Doctrine\DBAL\Driver\OCI8\OCI8Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if ( ! extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder('Doctrine\DBAL\Driver\OCI8\OCI8Connection')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        $this->assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}
