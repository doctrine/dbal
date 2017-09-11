<?php

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\Common\EventSubscriber;

/**
 * Session init listener for executing a single SQL statement right after a connection is opened.
 *
 * @link    www.doctrine-project.org
 * @since   2.2
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class SQLSessionInit implements EventSubscriber
{
    /**
     * @var string
     */
    protected $sql;

    /**
     * @param string $sql
     */
    public function __construct($sql)
    {
        $this->sql = $sql;
    }

    /**
     * @param \Doctrine\DBAL\Event\ConnectionEventArgs $args
     *
     * @return void
     */
    public function postConnect(ConnectionEventArgs $args)
    {
        $conn = $args->getConnection();
        $conn->exec($this->sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [Events::postConnect];
    }
}
