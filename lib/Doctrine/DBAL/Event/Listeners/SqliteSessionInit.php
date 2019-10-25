<?php declare(strict_types=1);

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 * Sqlite session init event subscriber enable foreign key constraints.
 */
class SqliteSessionInit implements EventSubscriber
{
    public function postConnect(ConnectionEventArgs $args) : void
    {
        $args->getConnection()->exec('PRAGMA foreign_keys = on');

        if (! ($args->getDatabasePlatform() instanceof SqlitePlatform)) {
            return;
        }

        $args->getDatabasePlatform()->enableForeignKeyConstraintsSupport();
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents() : array
    {
        return [Events::postConnect];
    }
}
