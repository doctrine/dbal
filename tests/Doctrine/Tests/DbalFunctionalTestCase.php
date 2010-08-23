<?php

namespace Doctrine\Tests;

class DbalFunctionalTestCase extends DbalTestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     *
     * @var Doctrine\DBAL\Connection
     */
    private static $_sharedConn;

    /**
     * @var Doctrine\DBAL\Connection
     */
    protected $_conn;

    protected function resetSharedConn()
    {
        if (isset($this->sharedFixture['conn'])) {
            $this->sharedFixture['conn']->close();
            $this->sharedFixture['conn'] = null;
        }
        if (self::$_sharedConn) {
            self::$_sharedConn->close();
            self::$_sharedConn = null;
        }
    }

    protected function setUp()
    {
        if (isset($this->sharedFixture['conn'])) {
            $this->_conn = $this->sharedFixture['conn'];
        } else {
            if ( ! isset(self::$_sharedConn)) {
                self::$_sharedConn = TestUtil::getConnection();
            }
            $this->_conn = self::$_sharedConn;
        }
    }
}
