<?php

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\Common\EventSubscriber;

/**
 * MySQL Session Init Event Subscriber which allows to set the Client Encoding of the Connection.
 *
 * @link       www.doctrine-project.org
 * @since      1.0
 * @author     Benjamin Eberlei <kontakt@beberlei.de>
 * @deprecated Use "charset" option to PDO MySQL Connection instead.
 */
class MysqlSessionInit implements EventSubscriber
{
    /**
     * The charset.
     *
     * @var string
     */
    private $_charset;

    /**
     * The collation, or FALSE if no collation.
     *
     * @var string|boolean
     */
    private $_collation;

    /**
     * Configure Charset and Collation options of MySQL Client for each Connection.
     *
     * @param string         $charset   The charset.
     * @param string|boolean $collation The collation, or FALSE if no collation.
     */
    public function __construct($charset = 'utf8', $collation = false)
    {
        $this->_charset = $charset;
        $this->_collation = $collation;
    }

    /**
     * @param \Doctrine\DBAL\Event\ConnectionEventArgs $args
     *
     * @return void
     */
    public function postConnect(ConnectionEventArgs $args)
    {
        $collation = ($this->_collation) ? " COLLATE ".$this->_collation : "";
        $args->getConnection()->executeUpdate("SET NAMES ".$this->_charset . $collation);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [Events::postConnect];
    }
}
