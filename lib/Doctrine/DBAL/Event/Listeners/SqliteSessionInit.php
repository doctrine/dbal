<?php declare(strict_types=1);

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;

/**
 * Sqlite session init event subscriber enable foreign key constraints.
 */
class SqliteSessionInit implements EventSubscriber
{
    public function postConnect(ConnectionEventArgs $args) : void
    {
        $args->getConnection()->exec('PRAGMA foreign_keys = on');
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents() : array
    {
        return [Events::postConnect];
    }
}
