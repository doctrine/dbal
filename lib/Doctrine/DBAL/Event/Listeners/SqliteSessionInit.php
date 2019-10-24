<?php declare(strict_types=1);

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\Common\EventSubscriber;

/**
 * Sqlite session init event subscriber enable foreign key constraints.
 */
class SqliteSessionInit implements EventSubscriber
{
    /**
     * @param \Doctrine\DBAL\Event\ConnectionEventArgs $args
     */
    public function postConnect(ConnectionEventArgs $args): void
    {
        $args->getConnection()->exec('PRAGMA foreign_keys = on');
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [Events::postConnect];
    }
}
