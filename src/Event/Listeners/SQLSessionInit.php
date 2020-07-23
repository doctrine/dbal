<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;

/**
 * Session init listener for executing a single SQL statement right after a connection is opened.
 */
class SQLSessionInit implements EventSubscriber
{
    /** @var string */
    protected $sql;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    /**
     * @throws DBALException
     */
    public function postConnect(ConnectionEventArgs $args): void
    {
        $args->getConnection()->executeStatement($this->sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [Events::postConnect];
    }
}
