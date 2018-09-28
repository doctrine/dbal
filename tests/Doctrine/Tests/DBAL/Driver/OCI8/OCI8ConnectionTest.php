<?php

namespace Doctrine\Tests\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\OCI8Connection;
use Doctrine\Tests\DbalTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use function extension_loaded;

class OCI8ConnectionTest extends DbalTestCase
{
    /**
     * The oci8 driver connection mock under test.
     *
     * @var OCI8Connection|PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if (! extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(OCI8Connection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}
