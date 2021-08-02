<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;

/**
 * Session init listener for executing a single SQL statement right after a connection is opened.
 */
class SQLSessionInit implements EventSubscriber
{
    protected string $sql;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    /**
     * @throws Exception
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
